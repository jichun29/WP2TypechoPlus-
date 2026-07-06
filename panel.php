<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$success = true;
try {
    $dbConfig = $options->plugin('WP2TypechoPlus');

    /** 初始化一个db */
    if (Typecho_Db_Adapter_Mysql::isAvailable()) {
        $wordpressDb = new Typecho_Db('Mysql', $dbConfig->prefix);
    } else {
        $wordpressDb = new Typecho_Db('Pdo_Mysql', $dbConfig->prefix);
    }

    /** 只读即可 */
    $wordpressDb->addServer(array (
      'host' => $dbConfig->host,
      'user' => $dbConfig->user,
      'password' => $dbConfig->password,
      'charset' => 'utf8mb4',
      'port' => $dbConfig->port,
      'database' => $dbConfig->database
    ), Typecho_Db::READ);
    
    $rows = $wordpressDb->fetchAll($wordpressDb->select()->from('table.options'));
    $static = array();
    foreach ($rows as $row) {
        $static[$row['option_name']] = $row['option_value'];
    }

    $posts = $wordpressDb->fetchAll($wordpressDb->select()->from('table.posts')
        ->where('post_type = ? OR post_type = ?', 'post', 'page'));
    $postStats = array();
    $latestPost = NULL;
    foreach ($posts as $post) {
        $key = $post['post_type'] . ' / ' . $post['post_status'];
        if (!isset($postStats[$key])) {
            $postStats[$key] = 0;
        }
        $postStats[$key] ++;
        if (NULL === $latestPost || $post['post_date'] > $latestPost) {
            $latestPost = $post['post_date'];
        }
    }
    ksort($postStats);
    $commentStats = array();
    $comments = $wordpressDb->fetchAll($wordpressDb->select('comment_approved')->from('table.comments'));
    foreach ($comments as $comment) {
        $key = '' === (string) $comment['comment_approved'] ? 'empty' : (string) $comment['comment_approved'];
        if (!isset($commentStats[$key])) {
            $commentStats[$key] = 0;
        }
        $commentStats[$key] ++;
    }
    ksort($commentStats);
} catch (Typecho_Db_Exception $e) {
    $success = false;
}

include 'header.php';
include 'menu.php';
?>
<div class="main">
    <div class="body body-950">
        <?php include 'page-title.php'; ?>
        <div class="container typecho-page-main">
            <div class="column-22 start-02">
                <?php if ($success): ?>
                <div class="message notice typecho-radius-topleft typecho-radius-topright typecho-radius-bottomleft typecho-radius-bottomright">
                    <?php _e('我们检测到了 WordPress 系统信息。请确认已备份当前 Typecho 数据库, 再点击下方按钮开始迁移。'); ?>
                    <blockquote>
                    <ul>
                        <li><strong><?php echo $static['blogname']; ?></strong></li>
                        <li><strong><?php echo $static['blogdescription']; ?></strong></li>
                        <li><strong><?php echo $static['siteurl']; ?></strong></li>
                    </ul>
                    </blockquote>
                    <p><strong><?php _e('可导入内容统计'); ?></strong></p>
                    <blockquote>
                    <ul>
                        <?php foreach ($postStats as $status => $count): ?>
                        <li><?php echo htmlspecialchars($status); ?>: <strong><?php echo $count; ?></strong></li>
                        <?php endforeach; ?>
                        <li><?php _e('最新文章时间'); ?>: <strong><?php echo htmlspecialchars($latestPost); ?></strong></li>
                    </ul>
                    </blockquote>
                    <p><strong><?php _e('评论状态统计'); ?></strong></p>
                    <blockquote>
                    <ul>
                        <?php foreach ($commentStats as $status => $count): ?>
                        <li><?php echo htmlspecialchars($status); ?>: <strong><?php echo $count; ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                    </blockquote>
                    <br />
                    <p><strong><?php _e('使用说明'); ?></strong></p>
                    <blockquote>
                    <ol>
                        <li><?php _e('先完整备份当前 Typecho 数据库。开始迁移会清空当前 Typecho 的文章、页面、评论、分类、标签和关系。'); ?></li>
                        <li><?php _e('在插件设置中填写旧 WordPress 数据库信息。本插件只读取旧库, 会写入当前 Typecho 正在使用的数据库。'); ?></li>
                        <li><?php _e('如需迁移图片, 请先把 WordPress 的 wp-content/uploads 目录内容复制到 Typecho 的 usr/uploads 目录。'); ?></li>
                        <li><?php _e('如更换过域名, 在插件设置中填写原 WordPress 站点地址, 导入时会替换正文和评论中的旧域名。'); ?></li>
                        <li><?php _e('导入过程中保持本页面打开。进度条完成前不要刷新页面或关闭浏览器标签页。'); ?></li>
                        <li><?php _e('WordPress 中非 publish 状态的文章会迁移为 Typecho 草稿, 导入后可在后台草稿箱检查。'); ?></li>
                        <li><?php _e('默认只导入已审核评论, 可在插件设置中调整评论导入范围。'); ?></li>
                    </ol>
                    </blockquote>
                    <br />
                    <div id="wp2typecho-plus-progress" style="display:none;margin:10px 0 0;">
                        <div style="height:18px;background:#eee;border-radius:3px;overflow:hidden;">
                            <div id="wp2typecho-plus-progress-bar" style="width:0;height:18px;background:#467b96;"></div>
                        </div>
                        <p id="wp2typecho-plus-status" style="margin-top:8px;"></p>
                    </div>
                    <p><button type="button" id="wp2typecho-plus-start"><?php _e('开始数据转换 &raquo;'); ?></button></p>
                </div>
                <?php else: ?>
                <div class="message error">
                    <?php _e('我们在连接到 Wordpress 的数据库时发生了错误, 请<a href="%s">重新设置</a>你的信息.', 
                    Typecho_Common::url('options-plugin.php?config=WP2TypechoPlus', $options->adminUrl)); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
include 'copyright.php';
include 'common-js.php';
if ($success): ?>
<script>
(function () {
    var startButton = document.getElementById('wp2typecho-plus-start');
    var progress = document.getElementById('wp2typecho-plus-progress');
    var progressBar = document.getElementById('wp2typecho-plus-progress-bar');
    var statusText = document.getElementById('wp2typecho-plus-status');
    var actionUrl = '<?php $options->index('/action/wp2typecho-plus'); ?>';
    var totals = {metas: 0, comments: 0, contents: 0};
    var completed = {metas: 0, comments: 0, contents: 0};

    function request(step, offset) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', actionUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) {
                return;
            }

            if (xhr.status < 200 || xhr.status >= 300) {
                fail('<?php _e('请求失败, 请检查服务器错误日志'); ?>');
                return;
            }

            var data;
            try {
                data = JSON.parse(xhr.responseText);
            } catch (e) {
                fail('<?php _e('导入接口返回异常, 可能是 PHP 报错或登录状态失效'); ?>');
                return;
            }

            if (!data.success) {
                fail(data.message || '<?php _e('导入失败'); ?>');
                return;
            }

            handle(data);
        };
        xhr.send('step=' + encodeURIComponent(step) + '&offset=' + encodeURIComponent(offset));
    }

    function handle(data) {
        if (data.totals) {
            totals = data.totals;
            completed = {metas: 0, comments: 0, contents: 0};
        }

        if (data.step && completed.hasOwnProperty(data.step) && typeof data.current !== 'undefined') {
            completed[data.step] = data.current;
        }

        updateProgress(data.message || '<?php _e('正在导入'); ?>');

        if (data.done) {
            startButton.disabled = false;
            startButton.innerHTML = '<?php _e('重新导入'); ?>';
            updateProgress('<?php _e('数据已经转换完成'); ?>');
            return;
        }

        request(data.nextStep, data.nextOffset || 0);
    }

    function updateProgress(message) {
        var total = totals.metas + totals.comments + totals.contents;
        var current = completed.metas + completed.comments + completed.contents;
        var percent = total > 0 ? Math.floor(current * 100 / total) : 0;

        progressBar.style.width = percent + '%';
        statusText.innerHTML = message + ' (' + current + '/' + total + ', ' + percent + '%)';
    }

    function fail(message) {
        startButton.disabled = false;
        statusText.innerHTML = '<span style="color:#c00;">' + message + '</span>';
    }

    startButton.onclick = function () {
        if (!confirm('<?php _e('开始导入会先清空当前 Typecho 文章、页面、评论、分类和标签。确认已经备份数据库了吗？'); ?>')) {
            return;
        }

        startButton.disabled = true;
        progress.style.display = 'block';
        progressBar.style.width = '0';
        statusText.innerHTML = '<?php _e('正在准备导入'); ?>';
        request('prepare', 0);
    };
})();
</script>
<?php endif;
include 'footer.php';
?>
