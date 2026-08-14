<?php
class TencentSmsClient {
    private $sdkAppId = '1401131153';
    private $appKey = '2fa5f57e8a0a418c18afba2dc531f02d';
    private $templateId = '2138875';
    
    public function __construct() {
    }
    
    public function sendVerificationCode($phone, $purpose = 'register') {
        $phone = trim((string)$phone);
        $purpose = $this->normalizePurpose($purpose);
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            return ['success' => false, 'message' => '手机号格式不正确', 'result' => -1, 'errmsg' => '手机号格式不正确'];
        }
        
        $code = $this->generateCode();
        $params = [$code];
        
        $result = $this->sendSms($phone, $params);
        if ($result['success']) {
            $record = [
                'phone' => $phone,
                'code' => $code,
                'purpose' => $purpose,
                'expire_at' => time() + 900,
                'send_time' => time()
            ];
            $_SESSION['sms_code'] = $record;
            try {
                $pdo = Database::connect();
                $this->ensureStorage($pdo);
                $stmt = $pdo->prepare("INSERT INTO sms_verifications(phone,purpose,code_hash,attempts,expire_at,sent_at) VALUES(?,?,?,0,FROM_UNIXTIME(?),NOW()) ON DUPLICATE KEY UPDATE code_hash=VALUES(code_hash),attempts=0,expire_at=VALUES(expire_at),sent_at=NOW()");
                $stmt->execute([$phone, $purpose, password_hash($code, PASSWORD_DEFAULT), $record['expire_at']]);
            } catch (Exception $e) {
                error_log('保存短信验证码失败: ' . $e->getMessage());
            }
        }
        $this->writeSendLog($phone, $purpose, $code, $result);
        
        return $result;
    }
    
    public function verifyCode($phone, $code, $purpose = 'register', $consume = true) {
        $phone = trim((string)$phone);
        $code = trim((string)$code);
        $purpose = $this->normalizePurpose($purpose);
        try {
            $pdo = Database::connect();
            $this->ensureStorage($pdo);
            $stmt = $pdo->prepare("SELECT * FROM sms_verifications WHERE phone=? AND purpose=? LIMIT 1");
            $stmt->execute([$phone, $purpose]);
            $stored = $stmt->fetch();
            if ($stored) {
                if (strtotime($stored['expire_at']) < time()) {
                    $pdo->prepare("DELETE FROM sms_verifications WHERE id=?")->execute([$stored['id']]);
                    return $this->result(false, '验证码已过期，请重新获取');
                }
                if (intval($stored['attempts']) >= 5) {
                    $pdo->prepare("DELETE FROM sms_verifications WHERE id=?")->execute([$stored['id']]);
                    return $this->result(false, '验证码错误次数过多，请重新获取');
                }
                if (!password_verify($code, $stored['code_hash'])) {
                    $pdo->prepare("UPDATE sms_verifications SET attempts=attempts+1 WHERE id=?")->execute([$stored['id']]);
                    return $this->result(false, '验证码不正确');
                }
                $this->markLogVerified($phone, $purpose, $code);
                if ($consume) {
                    $pdo->prepare("DELETE FROM sms_verifications WHERE id=?")->execute([$stored['id']]);
                    unset($_SESSION['sms_code']);
                }
                return $this->result(true, '验证成功');
            }
        } catch (Exception $e) {
            error_log('读取短信验证码失败: ' . $e->getMessage());
        }

        $smsCode = $_SESSION['sms_code'] ?? null;
        
        if (!$smsCode) {
            return $this->result(false, '请先获取验证码');
        }
        
        if ($smsCode['phone'] !== $phone || ($smsCode['purpose'] ?? 'register') !== $purpose) {
            return $this->result(false, '验证码与当前操作不匹配');
        }
        
        if (time() > $smsCode['expire_at']) {
            unset($_SESSION['sms_code']);
            return $this->result(false, '验证码已过期，请重新获取');
        }
        
        if ($smsCode['code'] !== $code) {
            return $this->result(false, '验证码不正确');
        }
        
        $this->markLogVerified($phone, $purpose, $code);
        if ($consume) unset($_SESSION['sms_code']);
        return $this->result(true, '验证成功');
    }

    public function consumeCode($phone, $purpose = 'register') {
        $phone = trim((string)$phone);
        $purpose = $this->normalizePurpose($purpose);
        try {
            $pdo = Database::connect();
            $this->ensureStorage($pdo);
            $stmt = $pdo->prepare("DELETE FROM sms_verifications WHERE phone=? AND purpose=?");
            $stmt->execute([$phone, $purpose]);
            $this->ensureLogStorage($pdo);
            $stmt = $pdo->prepare("UPDATE sms_send_logs SET consumed_at=NOW() WHERE phone=? AND purpose=? AND success=1 AND consumed_at IS NULL AND expire_at>=NOW() ORDER BY id DESC LIMIT 1");
            $stmt->execute([$phone, $purpose]);
        } catch (Exception $e) {
            error_log('消费短信验证码失败: ' . $e->getMessage());
        }
        $sessionCode = $_SESSION['sms_code'] ?? null;
        if ($sessionCode && $sessionCode['phone'] === $phone && ($sessionCode['purpose'] ?? 'register') === $purpose) {
            unset($_SESSION['sms_code']);
        }
    }
    
    public function canSendAgain($phone, $purpose = 'register') {
        $purpose = $this->normalizePurpose($purpose);
        try {
            $pdo = Database::connect();
            $this->ensureStorage($pdo);
            $stmt = $pdo->prepare("SELECT sent_at FROM sms_verifications WHERE phone=? AND purpose=? LIMIT 1");
            $stmt->execute([$phone, $purpose]);
            $sentAt = $stmt->fetchColumn();
            if ($sentAt) return time() - strtotime($sentAt) >= 60;
        } catch (Exception $e) {}
        $smsCode = $_SESSION['sms_code'] ?? null;
        if (!$smsCode || $smsCode['phone'] !== $phone || ($smsCode['purpose'] ?? 'register') !== $purpose) {
            return true;
        }
        return time() - $smsCode['send_time'] >= 60;
    }

    private function normalizePurpose($purpose) {
        $allowed = ['register', 'login', 'password_reset', 'profile_phone'];
        return in_array($purpose, $allowed, true) ? $purpose : 'register';
    }

    private function ensureStorage(PDO $pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `sms_verifications` (`id` BIGINT AUTO_INCREMENT PRIMARY KEY,`phone` VARCHAR(30) NOT NULL,`purpose` VARCHAR(32) NOT NULL,`code_hash` VARCHAR(255) NOT NULL,`attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,`expire_at` DATETIME NOT NULL,`sent_at` DATETIME NOT NULL,UNIQUE KEY `uk_sms_phone_purpose` (`phone`,`purpose`),INDEX `idx_sms_expire` (`expire_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function ensureLogStorage(PDO $pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `sms_send_logs` (`id` BIGINT AUTO_INCREMENT PRIMARY KEY,`phone` VARCHAR(30) NOT NULL,`purpose` VARCHAR(32) NOT NULL,`code_plain` VARCHAR(12) NULL,`content` VARCHAR(255) NULL,`success` TINYINT(1) NOT NULL DEFAULT 0,`provider_message` VARCHAR(255) NULL,`sent_at` DATETIME NOT NULL,`expire_at` DATETIME NULL,`verified_at` DATETIME NULL,`consumed_at` DATETIME NULL,INDEX `idx_sms_log_phone` (`phone`,`sent_at`),INDEX `idx_sms_log_status` (`purpose`,`success`,`expire_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function writeSendLog($phone, $purpose, $code, $result) {
        try {
            $pdo = Database::connect();
            $this->ensureLogStorage($pdo);
            $content = '验证码：' . $code . '，15分钟内有效';
            $stmt = $pdo->prepare("INSERT INTO sms_send_logs(phone,purpose,code_plain,content,success,provider_message,sent_at,expire_at) VALUES(?,?,?,?,?,?,NOW(),DATE_ADD(NOW(),INTERVAL 15 MINUTE))");
            $stmt->execute([$phone,$purpose,$result['success']?$code:null,$content,$result['success']?1:0,substr((string)($result['message']??$result['errmsg']??''),0,255)]);
        } catch (Throwable $e) {
            error_log('记录短信发送日志失败: ' . $e->getMessage());
        }
    }

    private function markLogVerified($phone, $purpose, $code) {
        try {
            $pdo = Database::connect();
            $this->ensureLogStorage($pdo);
            $stmt = $pdo->prepare("UPDATE sms_send_logs SET verified_at=COALESCE(verified_at,NOW()) WHERE phone=? AND purpose=? AND code_plain=? AND success=1 ORDER BY id DESC LIMIT 1");
            $stmt->execute([$phone,$purpose,$code]);
        } catch (Throwable $e) {}
    }

    private function result($success, $message) {
        return ['success' => (bool)$success, 'message' => $message, 'result' => $success ? 0 : -1, 'errmsg' => $success ? 'OK' : $message];
    }
    
    private function generateCode() {
        return str_pad((string)mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    private function sendSms($phone, $params) {
        try {
            $url = 'https://yun.tim.qq.com/v5/tlssmssvr/sendsms';
            $random = $this->random();
            $curTime = time();
            
            // 腾讯云签名计算: appkey=xxx&random=xxx&time=xxx&mobile=xxx
            $sigStr = "appkey=" . $this->appKey . "&random=" . $random . "&time=" . $curTime . "&mobile=" . $phone;
            $sig = hash('sha256', $sigStr);
            
            $data = [
                'ext' => '',
                'extend' => '',
                'params' => $params,
                'sig' => $sig,
                'sign' => '语落绘墨',
                'tel' => [
                    'mobile' => $phone,
                    'nationcode' => '86'
                ],
                'time' => $curTime,
                'tpl_id' => $this->templateId
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url . "?sdkappid=" . $this->sdkAppId . "&random=" . $random);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($data))
            ]);
            
            $response = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($errno) {
                return ['success' => false, 'message' => '发送失败：' . $error, 'result' => -1, 'errmsg' => $error];
            }
            
            $result = json_decode($response, true);
            // 调试输出完整响应
            if (defined('DEBUG') && DEBUG) {
                error_log('腾讯云短信响应: ' . $response);
            }
            
            // 腾讯云短信API返回 result=0 表示成功
            if ($result && isset($result['result']) && $result['result'] === 0) {
                return [
                    'success' => true,
                    'message' => '发送成功',
                    'result' => $result['result'],
                    'errmsg' => $result['errmsg'] ?? '',
                    'raw_response' => $result
                ];
            }
            
            // 常见错误码处理
            $tencentResult = $result['result'] ?? -1;
            $tencentErrmsg = $result['errmsg'] ?? '';
            $msg = $this->getErrorMessage($tencentResult, $tencentErrmsg) . ' (result=' . $tencentResult . ')';
            return [
                'success' => false,
                'message' => $msg,
                'result' => $tencentResult,
                'errmsg' => $tencentErrmsg,
                'raw_response' => $result
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => '发送异常：' . $e->getMessage(), 'result' => -1, 'errmsg' => $e->getMessage()];
        }
    }
    
    private function getErrorMessage($result, $errmsg) {
        $errors = [
            1016 => '短信签名格式错误或未审核通过',
            1017 => '请求的短信模板不存在或未审核通过',
            1021 => '手机号格式错误',
            2008 => '手机号在黑名单中',
            3001 => '短信签名或模板不正确',
            -1 => '系统异常'
        ];
        
        if (isset($errors[$result])) {
            return $errors[$result];
        }
        return $errmsg ?: '发送失败';
    }
    
    private function random() {
        $str = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $result = '';
        for ($i = 0; $i < 10; $i++) {
            $result .= $str[mt_rand(0, strlen($str) - 1)];
        }
        return $result;
    }
}
