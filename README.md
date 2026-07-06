<img width="1536" height="1024" alt="WP2TypechoPlus" src="https://github.com/user-attachments/assets/7c36d5ec-83e6-44ee-a8cb-549f3e249274" />


# WP2Typecho Plus

WP2Typecho Plus 是基于 Typecho 官方文档中 WordPress to Typecho 迁移插件二次优化的版本, 用于将 WordPress 数据库中的文章、页面、评论、分类和标签迁移到当前 Typecho 站点。

作者: jichun29

主页: https://sound.jichun29.cn/pages/sites.php

## 功能特性

- 支持从旧 WordPress 数据库迁移到当前 Typecho 数据库。
- 支持文章、页面、评论、分类、标签和分类标签关系迁移。
- 使用 utf8mb4 连接旧 WordPress 数据库, 更适合包含 emoji 或特殊字符的内容。
- 支持分批 AJAX 导入, 后台显示进度条, 降低大量数据导入时超时或白屏的概率。
- 支持导入前显示可导入内容统计和最新文章时间。
- 支持把正文和评论中的旧站点域名替换为当前 Typecho 站点域名。
- 支持把 WordPress 图片路径 `/wp-content/uploads/` 替换为 Typecho 图片路径 `/usr/uploads/`。
- 非 `publish` 状态的 WordPress 文章会迁移为 Typecho 草稿。

## 使用方法

1. 备份当前 Typecho 数据库。
2. 将 `WP2TypechoPlus` 目录上传到 Typecho 的 `usr/plugins/` 目录。
3. 进入 Typecho 后台启用插件 `WP2Typecho Plus`。
4. 在插件设置中填写旧 WordPress 数据库地址、端口、用户名、密码、数据库名和表前缀。
5. 如旧站更换过域名, 填写原 WordPress 站点地址, 例如 `https://old.example.com`。
6. 如需迁移图片, 先将旧 WordPress 的 `wp-content/uploads` 目录内容复制到当前 Typecho 的 `usr/uploads` 目录。
7. 进入 `WP2Typecho Plus` 面板, 确认可导入数量和最新文章时间。
8. 点击开始迁移, 保持页面打开直到进度条完成。

## 重要说明

- 开始迁移会清空当前 Typecho 的文章、页面、评论、分类、标签和关系数据。
- 插件设置中填写的是旧 WordPress 数据库信息, 旧库只会被读取。
- 数据会写入当前 Typecho 站点正在使用的数据库。
- 插件不会复制图片文件本身, 只会替换文章和评论中的图片路径。
- 图片目录应类似 `usr/uploads/2025/08/example.png`, 不应是 `usr/uploads/wp-content/uploads/2025/08/example.png`。

## 升级记录

### 2.0.0

- 将原一次性导入改为分阶段、分批导入。
- 新增后台进度条和导入状态提示。
- 新增导入前数据统计, 可查看文章页面状态分布和最新文章时间。
- 数据库连接字符集升级为 utf8mb4。
- 新增旧域名到当前站点域名的内容替换。
- 新增 WordPress 上传目录到 Typecho 上传目录的路径替换。
- 修复原插件激活提示中的未定义变量问题。
- 文章作者统一归属为当前执行迁移的 Typecho 管理员, 避免旧 WordPress 作者 ID 在 Typecho 不存在导致异常。
- 增强单批导入的可重试能力, 同 ID 目标记录会先删除再写入。
