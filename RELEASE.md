# Brave Love v1.2.3

Brave Love `v1.2.3` 是一次以仓库展示和几处实际使用问题为重点的 maintenance release。

这次不涉及数据库迁移，也不改现有后台内容结构。

## 本次发布亮点

### 1) GitHub 首页不再塞整本说明书
- README 收成一页：这是什么、演示、安装、手册入口
- 完整操作说明仍在 `docs/USER-GUIDE.md`
- 根目录补上 `LICENSE`，GitHub 可以识别 GPL

### 2) 祝福留言不再误伤管理员
- 具备 `moderate_comments` 权限的登录用户，自己提交的留言会直接通过
- 访客留言仍默认进入审核

### 3) 天气接口增加基础限流
- 公开的 `brave-love/v1/weather` 按 IP 限制为每分钟 30 次
- 超限返回 `429`，避免把主题站点当成和风天气的免费代理

### 4) 页脚版权指向当前站点
- 站点名称不再链到写死的 `https://www.1ink.ink/`
- 改为链到当前站点首页

## 重点变更文件

- `README.md`
- `LICENSE`
- `functions.php`
- `footer.php`
- `inc/weather/rest.php`
- `style.css`
- `CHANGELOG.md`
- `RELEASE.md`
- `.github/ABOUT.md`

## 升级说明

如果你正在使用 `v1.2.2`：

1. 这次无需做数据库迁移
2. 现有页面和后台配置可直接继续使用
3. 如果你在页脚依赖“点站点名跳到 1ink.ink”，升级后会改成跳当前站点
4. 天气接口若被异常高频访问，可能看到 429；正常首页轮询不受影响

## 发布资产

- `brave-love.zip`
- GitHub Release Notes（当前文件）
- `CHANGELOG.md`

**版本**: `1.2.3`
**发布日期**: `2026-09-29`
**更新日志**: `CHANGELOG.md`

