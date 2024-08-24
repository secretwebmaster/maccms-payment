# MACCMS万用支付接口模板
![maccms-payment-screenshot](https://github.com/user-attachments/assets/8db10920-4b6b-48a7-82ac-9ddad0a7ad47)

## 安裝說明
- PHP 7.2+ ![PHP Version](https://img.shields.io/badge/php-%3E%3D%207.2-8892BF.svg)
- 支持任意 maccms V10 版本
- Wnpay.php 上傳到 application/common/extend/pay/
- wnpay.html 上傳到 application/admin/view/extend/pay/

## 操作說明

### 配置接口
- 登入maccms後台
- 前往 系統 >  在線支付配置 > 在線支付
- 修改支付接口，API信息
### 修改接口名称
```php
public $name = '在线支付';
```
### 簽名邏輯
```php
public function sign($data)
{
    //your logic here
}
```
### Debug模式
$debug property 設定爲true，下單/回調時截獲後，會在頁面中顯示截獲的資訊，終止支付跳轉
```php
    public $debug = true;
```
### 日誌
$log property 設定爲true，下單/回調時截獲後，會將截獲的資訊保存到 /runtime/log 目錄中
```php
    public $log = true;
```

## 更新日誌
### v2.0.0 - 2024/8/24
- 新版本上線
