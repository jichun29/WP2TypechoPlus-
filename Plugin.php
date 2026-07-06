<?php
/**
 * 将 WordPress 数据库中的内容分批迁移到 Typecho
 * 
 * @package WP2Typecho Plus
 * @author jichun29
 * @version 2.0.0
 * @link https://sound.jichun29.cn/pages/sites.php
 */
class WP2TypechoPlus_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        $error = NULL;

        if (!Typecho_Db_Adapter_Mysql::isAvailable() && !Typecho_Db_Adapter_Pdo_Mysql::isAvailable()) {
            throw new Typecho_Plugin_Exception(_t('没有找到任何可用的 Mysql 适配器'));
        }
        
        /**$error = NULL;
        if ((!is_dir(__TYPECHO_ROOT_DIR__ . '/usr/uploads/') || !is_writeable(__TYPECHO_ROOT_DIR__ . '/usr/uploads/'))
        && !is_writeable(__TYPECHO_ROOT_DIR__ . '/usr/')) {
            $error = '<br /><strong>' . _t('%s 目录不可写, 可能会导致附件转换不成功', __TYPECHO_ROOT_DIR__ . '/usr/uploads/') . '</strong>';
        }
		*/
    
        Helper::addPanel(1, 'WP2TypechoPlus/panel.php', _t('WP2Typecho Plus'), _t('WP2Typecho Plus'), 'administrator');
        Helper::addAction('wp2typecho-plus', 'WP2TypechoPlus_Action');
        return _t('请在插件设置里填写旧 WordPress 数据库参数, 再进入 WP2Typecho Plus 面板开始迁移') . $error;
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        Helper::removeAction('wp2typecho-plus');
        Helper::removePanel(1, 'WP2TypechoPlus/panel.php');
    }
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $guide = new Typecho_Widget_Helper_Form_Element_Text('guide', NULL, 'https://blog.jichun29.cn/6375.html',
        _t('使用教程地址'), _t('插件使用教程: <a href="https://blog.jichun29.cn/6375.html" target="_blank">https://blog.jichun29.cn/6375.html</a>'));
        $guide->input->setAttribute('readonly', 'readonly');
        $form->addInput($guide);

        $host = new Typecho_Widget_Helper_Form_Element_Text('host', NULL, 'localhost',
        _t('旧 WordPress 数据库地址'), _t('填写旧 WordPress 数据库服务器地址, 例如 localhost 或数据库内网地址'));
        $form->addInput($host->addRule('required', _t('必须填写一个数据库地址')));
        
        $port = new Typecho_Widget_Helper_Form_Element_Text('port', NULL, '3306',
        _t('旧 WordPress 数据库端口'), _t('旧 WordPress 数据库服务器端口'));
        $port->input->setAttribute('class', 'mini');
        $form->addInput($port->addRule('required', _t('必须填写数据库端口'))
        ->addRule('isInteger', _t('端口号必须是纯数字')));
        
        $user = new Typecho_Widget_Helper_Form_Element_Text('user', NULL, 'root',
        _t('旧 WordPress 数据库用户名'));
        $form->addInput($user->addRule('required', _t('必须填写数据库用户名')));
        
        $password = new Typecho_Widget_Helper_Form_Element_Password('password', NULL, NULL,
        _t('旧 WordPress 数据库密码'));
        $form->addInput($password);
        
        $database = new Typecho_Widget_Helper_Form_Element_Text('database', NULL, 'Wordpress',
        _t('旧 WordPress 数据库名称'), _t('旧 WordPress 所在的数据库名称'));
        $form->addInput($database->addRule('required', _t('您必须填写数据库名称')));
    
        $prefix = new Typecho_Widget_Helper_Form_Element_Text('prefix', NULL, 'wp_',
        _t('旧 WordPress 表前缀'), _t('旧 WordPress 数据表前缀, 常见为 wp_'));
        $form->addInput($prefix->addRule('required', _t('您必须填写表前缀')));

        $sourceUrl = new Typecho_Widget_Helper_Form_Element_Text('sourceUrl', NULL, '',
        _t('原 WordPress 站点地址'), _t('可选, 用于把正文中的旧域名替换为当前 Typecho 站点地址, 例如 https://old.example.com'));
        $form->addInput($sourceUrl);

        $commentStatus = new Typecho_Widget_Helper_Form_Element_Select('commentStatus', array(
            'approved' => _t('只导入已审核评论, 跳过垃圾评论、回收站评论和待审核评论'),
            'visible' => _t('导入已审核和待审核评论, 跳过垃圾评论和回收站评论'),
            'all' => _t('导入全部评论')
        ), 'approved', _t('评论导入范围'), _t('建议保持默认, 避免 WordPress 垃圾评论同步到 Typecho'));
        $form->addInput($commentStatus);
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
}
