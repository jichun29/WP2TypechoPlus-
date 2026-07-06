<?php

class WP2TypechoPlus_Action extends Typecho_Widget implements Widget_Interface_Do
{
    private $batchSize = 100;

    public function doImport()
    {
        try {
            $step = isset($_POST['step']) ? $_POST['step'] : 'prepare';
            $offset = isset($_POST['offset']) ? max(0, intval($_POST['offset'])) : 0;

            $options = $this->widget('Widget_Options');
            $sourceDb = $this->getWordpressDb($options);
            $targetDb = Typecho_Db::get();

            switch ($step) {
                case 'prepare':
                    $data = $this->prepareImport($sourceDb, $targetDb);
                    break;
                case 'metas':
                    $data = $this->importMetas($sourceDb, $targetDb, $offset);
                    break;
                case 'comments':
                    $data = $this->importComments($sourceDb, $targetDb, $offset, $options);
                    break;
                case 'contents':
                    $data = $this->importContents($sourceDb, $targetDb, $offset, $options);
                    break;
                default:
                    throw new Typecho_Exception(_t('未知的导入阶段'));
            }

            $this->json(array_merge(array('success' => true), $data));
        } catch (Exception $e) {
            $this->json(array(
                'success' => false,
                'message' => $e->getMessage()
            ));
        }
    }

    private function getWordpressDb($options)
    {
        $dbConfig = $options->plugin('WP2TypechoPlus');

        if (Typecho_Db_Adapter_Mysql::isAvailable()) {
            $db = new Typecho_Db('Mysql', $dbConfig->prefix);
        } else {
            $db = new Typecho_Db('Pdo_Mysql', $dbConfig->prefix);
        }

        $db->addServer(array(
            'host' => $dbConfig->host,
            'user' => $dbConfig->user,
            'password' => $dbConfig->password,
            'charset' => 'utf8mb4',
            'port' => $dbConfig->port,
            'database' => $dbConfig->database
        ), Typecho_Db::READ);

        return $db;
    }

    private function prepareImport($sourceDb, $targetDb)
    {
        $this->widget('Widget_Abstract_Contents')->to($contents)->delete($targetDb->sql()->where('1 = 1'));
        $this->widget('Widget_Abstract_Comments')->to($comments)->delete($targetDb->sql()->where('1 = 1'));
        $this->widget('Widget_Abstract_Metas')->to($metas)->delete($targetDb->sql()->where('1 = 1'));
        $targetDb->query($targetDb->delete('table.relationships')->where('1 = 1'));

        return array(
            'step' => 'prepare',
            'nextStep' => 'metas',
            'nextOffset' => 0,
            'totals' => array(
                'metas' => $this->countMetas($sourceDb),
                'comments' => $this->countComments($sourceDb, $this->getCommentStatusMode()),
                'contents' => $this->countContents($sourceDb)
            ),
            'message' => _t('已清空当前 Typecho 内容, 开始导入分类和标签')
        );
    }

    private function importMetas($sourceDb, $targetDb, $offset)
    {
        $total = $this->countMetas($sourceDb);
        $page = intval($offset / $this->batchSize) + 1;
        $rows = $sourceDb->fetchAll($sourceDb->select()->from('table.term_taxonomy')
            ->join('table.terms', 'table.term_taxonomy.term_id = table.terms.term_id')
            ->where('taxonomy = ? OR taxonomy = ?', 'category', 'post_tag')
            ->order('term_taxonomy_id', Typecho_Db::SORT_ASC)
            ->page($page, $this->batchSize));

        foreach ($rows as $term) {
            $targetDb->query($targetDb->delete('table.relationships')->where('mid = ?', $term['term_taxonomy_id']));
            $targetDb->query($targetDb->delete('table.metas')->where('mid = ?', $term['term_taxonomy_id']));
            $targetDb->query($targetDb->insert('table.metas')->rows(array(
                'mid' => $term['term_taxonomy_id'],
                'name' => $term['name'],
                'slug' => 'post_tag' == $term['taxonomy'] ? Typecho_Common::slugName($term['name']) : $term['slug'],
                'type' => 'post_tag' == $term['taxonomy'] ? 'tag' : 'category',
                'description' => $term['description'],
                'count' => $term['count']
            )));

            $relationships = $sourceDb->fetchAll($sourceDb->select()->from('table.term_relationships')
                ->where('term_taxonomy_id = ?', $term['term_taxonomy_id']));
            foreach ($relationships as $relationship) {
                $targetDb->query($targetDb->insert('table.relationships')->rows(array(
                    'cid' => $relationship['object_id'],
                    'mid' => $relationship['term_taxonomy_id']
                )));
            }
        }

        $nextOffset = $offset + count($rows);
        return $this->batchResult('metas', 'comments', $offset, $nextOffset, $total, _t('分类和标签导入完成, 开始导入评论'));
    }

    private function importComments($sourceDb, $targetDb, $offset, $options)
    {
        $commentStatusMode = $this->getCommentStatusMode();
        $total = $this->countComments($sourceDb, $commentStatusMode);
        $page = intval($offset / $this->batchSize) + 1;
        $query = $this->applyCommentStatusFilter($sourceDb->select()->from('table.comments'), $commentStatusMode);
        $rows = $sourceDb->fetchAll($query->order('comment_ID', Typecho_Db::SORT_ASC)->page($page, $this->batchSize));
        $gmtOffset = idate('Z');
        list($sourceUrls, $targetUrl) = $this->getUrlReplaceConfig($sourceDb, $options);

        foreach ($rows as $row) {
            $status = $this->mapCommentStatus($row['comment_approved']);
            $text = $this->replaceContentUrl($row['comment_content'], $sourceUrls, $targetUrl);
            $text = preg_replace(
                array('/\s*<p>/is', '/\s*<\/p>\s*/is', '/\s*<br\s*\/>\s*/is', '/\s*<(div|blockquote|pre|table|ol|ul)>/is', '/<\/(div|blockquote|pre|table|ol|ul)>\s*/is'),
                array('', "\n\n", "\n", "\n\n<\\1>", "</\\1>\n\n"),
                $text
            );

            $targetDb->query($targetDb->delete('table.comments')->where('coid = ?', $row['comment_ID']));
            $targetDb->query($targetDb->insert('table.comments')->rows(array(
                'coid' => $row['comment_ID'],
                'cid' => $row['comment_post_ID'],
                'created' => $this->normalizeTimestamp($row['comment_date_gmt'], $gmtOffset),
                'author' => $row['comment_author'],
                'authorId' => $row['user_id'],
                'ownerId' => 1,
                'mail' => $row['comment_author_email'],
                'url' => $row['comment_author_url'],
                'ip' => $row['comment_author_IP'],
                'agent' => $row['comment_agent'],
                'text' => $text,
                'type' => empty($row['comment_type']) ? 'comment' : $row['comment_type'],
                'status' => $status,
                'parent' => $row['comment_parent']
            )));
        }

        $nextOffset = $offset + count($rows);
        return $this->batchResult('comments', 'contents', $offset, $nextOffset, $total, _t('评论导入完成, 开始导入文章和页面'));
    }

    private function importContents($sourceDb, $targetDb, $offset, $options)
    {
        $total = $this->countContents($sourceDb);
        $page = intval($offset / $this->batchSize) + 1;
        $rows = $sourceDb->fetchAll($sourceDb->select()->from('table.posts')
            ->where('post_type = ? OR post_type = ?', 'post', 'page')
            ->order('ID', Typecho_Db::SORT_ASC)
            ->page($page, $this->batchSize));
        $gmtOffset = idate('Z');
        $userId = $this->widget('Widget_User')->uid;
        list($sourceUrls, $targetUrl) = $this->getUrlReplaceConfig($sourceDb, $options);

        foreach ($rows as $row) {
            $targetDb->query($targetDb->delete('table.contents')->where('cid = ?', $row['ID']));
            $targetDb->query($targetDb->insert('table.contents')->rows(array(
                'cid' => $row['ID'],
                'title' => $this->normalizeTitle($row['post_title'], $row['ID']),
                'slug' => Typecho_Common::slugName(urldecode($row['post_name']), $row['ID'], 128),
                'created' => $this->normalizeTimestamp($row['post_date_gmt'], $gmtOffset),
                'modified' => $this->normalizeTimestamp($row['post_modified_gmt'], $gmtOffset),
                'text' => $this->replaceContentUrl($row['post_content'], $sourceUrls, $targetUrl),
                'order' => $row['menu_order'],
                'authorId' => $userId,
                'template' => NULL,
                'type' => 'page' == $row['post_type'] ? 'page' : 'post',
                'status' => 'publish' == $row['post_status'] ? 'publish' : 'draft',
                'password' => $row['post_password'],
                'commentsNum' => $row['comment_count'],
                'allowComment' => 'open' == $row['comment_status'] ? '1' : '0',
                'allowFeed' => '1',
                'allowPing' => 'open' == $row['ping_status'] ? '1' : '0'
            )));
        }

        $nextOffset = $offset + count($rows);
        return $this->batchResult('contents', 'done', $offset, $nextOffset, $total, _t('数据已经转换完成'));
    }

    private function batchResult($step, $nextStep, $offset, $nextOffset, $total, $doneMessage)
    {
        $done = $nextOffset >= $total;

        return array(
            'step' => $step,
            'nextStep' => $done ? $nextStep : $step,
            'nextOffset' => $done ? 0 : $nextOffset,
            'current' => min($nextOffset, $total),
            'total' => $total,
            'done' => $done && 'done' == $nextStep,
            'message' => $done ? $doneMessage : _t('正在导入')
        );
    }

    private function countMetas($db)
    {
        $row = $db->fetchRow($db->select(array('COUNT(*)' => 'num'))->from('table.term_taxonomy')
            ->where('taxonomy = ? OR taxonomy = ?', 'category', 'post_tag'));
        return intval($row['num']);
    }

    private function countComments($db, $commentStatusMode)
    {
        $query = $this->applyCommentStatusFilter($db->select(array('COUNT(*)' => 'num'))->from('table.comments'), $commentStatusMode);
        $row = $db->fetchRow($query);
        return intval($row['num']);
    }

    private function applyCommentStatusFilter($query, $commentStatusMode)
    {
        if ('approved' === $commentStatusMode) {
            return $query->where('comment_approved = ?', '1');
        }

        if ('visible' === $commentStatusMode) {
            return $query->where('comment_approved = ? OR comment_approved = ?', '1', '0');
        }

        return $query;
    }

    private function getCommentStatusMode()
    {
        $options = $this->widget('Widget_Options');
        $dbConfig = $options->plugin('WP2TypechoPlus');
        return empty($dbConfig->commentStatus) ? 'approved' : $dbConfig->commentStatus;
    }

    private function countContents($db)
    {
        $row = $db->fetchRow($db->select(array('COUNT(*)' => 'num'))->from('table.posts')
            ->where('post_type = ? OR post_type = ?', 'post', 'page'));
        return intval($row['num']);
    }

    private function getUrlReplaceConfig($sourceDb, $options)
    {
        $dbConfig = $options->plugin('WP2TypechoPlus');
        $sourceUrls = array();
        if (!empty($dbConfig->sourceUrl)) {
            $sourceUrls[] = $dbConfig->sourceUrl;
        }

        $wpOptions = $sourceDb->fetchAll($sourceDb->select()->from('table.options')
            ->where('option_name = ? OR option_name = ?', 'siteurl', 'home'));
        foreach ($wpOptions as $wpOption) {
            if (!empty($wpOption['option_value'])) {
                $sourceUrls[] = $wpOption['option_value'];
            }
        }

        return array(array_unique(array_filter($sourceUrls)), rtrim($options->siteUrl, '/'));
    }

    private function replaceContentUrl($text, array $sourceUrls, $targetUrl)
    {
        foreach ($sourceUrls as $sourceUrl) {
            $sourceUrl = rtrim($sourceUrl, '/');
            if ('' === $sourceUrl || $sourceUrl === $targetUrl) {
                continue;
            }

            $text = str_replace($sourceUrl, $targetUrl, $text);
            $text = str_replace(str_replace('https://', 'http://', $sourceUrl), $targetUrl, $text);
            $text = str_replace(str_replace('http://', 'https://', $sourceUrl), $targetUrl, $text);
        }

        $text = str_replace($targetUrl . '/wp-content/uploads/', $targetUrl . '/usr/uploads/', $text);
        $text = str_replace('/wp-content/uploads/', '/usr/uploads/', $text);

        return $text;
    }

    private function mapCommentStatus($status)
    {
        if ('spam' == $status) {
            return 'spam';
        }

        if ('0' == $status) {
            return 'waiting';
        }

        return 'approved';
    }

    private function normalizeTimestamp($date, $gmtOffset)
    {
        $timestamp = false;
        if (!empty($date) && '0000-00-00 00:00:00' !== $date) {
            $timestamp = strtotime($date);
        }

        if (false === $timestamp || $timestamp <= 0) {
            $timestamp = time();
        } else {
            $timestamp += $gmtOffset;
        }

        if ($timestamp < 1) {
            return 1;
        }

        if ($timestamp > 2147483647) {
            return 2147483647;
        }

        return $timestamp;
    }

    private function normalizeTitle($title, $id)
    {
        $title = trim((string) $title);
        return '' === $title ? 'Untitled-' . $id : $title;
    }

    private function json(array $data)
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data);
        exit;
    }

    public function action()
    {
        $this->widget('Widget_User')->pass('administrator');
        $this->on($this->request->isPost())->doImport();
    }
}
