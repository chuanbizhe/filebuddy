# FileBuddy 支付接口

FileBuddy 使用支付宝 RSA2 当面付预创建接口生成二维码。私钥、支付宝公钥和 App ID 只保存在服务端环境变量，客户端不会收到任何密钥。

## 套餐

`GET /v1/billing/plans` 返回月度（30 天）和年度（365 天）连接服务套餐。

## 创建订单

```http
POST /v1/billing/orders
Authorization: Bearer <登录令牌>
Content-Type: application/json

{"plan":"monthly"}
```

成功返回 `orderNo`、`amount`、`status` 和支付宝 `qrCode`。二维码有效期为 2 小时。

## 查询订单与权益

- `GET /v1/billing/orders/{orderNo}`：只能查询当前用户自己的订单。
- `GET /v1/billing/status`：返回当前套餐、是否有效以及到期时间。

支付宝异步通知地址为 `/v1/billing/alipay/notify`。服务端会校验 RSA2 签名、订单号和金额，幂等地更新订单，并延长用户套餐有效期；重复通知不会重复延长。

## 配置

生产环境通过 `ALIPAY_APP_ID`、`ALIPAY_PRIVATE_KEY`、`ALIPAY_PUBLIC_KEY` 注入，不要提交 `.env` 或任何密钥。支付宝应用应仅开启当面付所需权限，并限制通知地址为 HTTPS。
