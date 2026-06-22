<?php

namespace RamboWoon;

class RemoteClient
{
    public static function post($url, $data = [], $auth = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600); // 10 minutes timeout for large tasks
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36');

        if ($auth) {
            curl_setopt($ch, CURLOPT_USERPWD, $auth);
        }

        // Keep POST data on redirects (301, 302, 303)
        curl_setopt($ch, CURLOPT_POSTREDIR, 3); 

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($res === false || $httpCode >= 400) {
            $msg = $res === false ? "CURL Error: $error" : "HTTP Error $httpCode";
            return json_encode(['status' => 'error', 'message' => $msg, 'body' => $res]);
        }

        return $res;
    }
    
    public static function get($url, $auth = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (RamboWoonBridge)');

        if ($auth) {
            curl_setopt($ch, CURLOPT_USERPWD, $auth);
        }

        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }

    public static function postJson($url, $data = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['payload' => json_encode($data)]); // Wrap in payload for bridge if needed or direct
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }

    public static function downloadFile($url, $destPath)
    {
        $fp = @fopen($destPath, 'w+');
        if (!$fp) {
            $err = error_get_last();
            return "Failed to open destination path for writing: " . $destPath . " | Reason: " . ($err['message'] ?? 'Unknown error');
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($res === false) {
            @unlink($destPath);
            return "Curl Error: " . $error;
        }
        if ($httpCode >= 400) {
            @unlink($destPath);
            return "HTTP Error " . $httpCode . " during file download.";
        }
        return true;
    }

    public static function uploadFtp($url, $userPwd, $localPath)
    {
        $url = str_replace(' ', '', $url);
        $localPath = trim($localPath);
        if (!file_exists($localPath)) return "Lỗi: Không tìm thấy tệp tin local để upload: " . basename($localPath);
        $fsize = filesize($localPath);
        $ch = curl_init();
        $fp = fopen($localPath, 'r');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, $userPwd);
        curl_setopt($ch, CURLOPT_UPLOAD, 1);
        curl_setopt($ch, 110, 0);
        curl_setopt($ch, 151, 0);
        curl_setopt($ch, 113, 1);
        curl_setopt($ch, 121, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3600); // Increased to 1 hour for large files like dist.zip (700MB+)
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        if (defined('CURLOPT_USE_SSL')) curl_setopt($ch, CURLOPT_USE_SSL, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_INFILE, $fp);
        curl_setopt($ch, CURLOPT_INFILESIZE, $fsize);
        curl_setopt($ch, CURLOPT_FTP_CREATE_MISSING_DIRS, 2); // Enable robust auto-creation/navigation
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $closeStatus = curl_close($ch);
        fclose($fp);

        if ($res === false) {
            $msg = $error ?: "Unknown FTP Error (HTTP/FTP Code: $httpCode)";
            return "FTP Error on [$url]: $msg";
        }
        return true;
    }

    public static function checkFileExistsFtp($url, $userPwd)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, $userPwd);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $closeStatus = curl_close($ch);
        return $httpCode === 200; // Success code for file entry found
    }

    public static function listFtpDirectory($url, $userPwd)
    {
        $url = rtrim($url, '/') . '/';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, $userPwd);
        curl_setopt($ch, CURLOPT_DIRLISTONLY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        $closeStatus = curl_close($ch);

        if ($res === false) return [];

        $lines = explode("\n", trim($res));
        return array_map('trim', $lines);
    }

    private static function executeDA($config, $path, $data = null, $isPost = true)
    {
        // Automatically resolve relative paths (like /public_html) to absolute DirectAdmin paths
        if (is_array($data) && isset($data['path'])) {
            $domain = !empty($config['web_domain'])
                ? str_replace(['https://', 'http://', '/'], '', $config['web_domain'])
                : str_replace(['ftp.', 'www.'], '', $config['ftp_host']);
            
            $srvPath = $data['path'];
            if (strpos($srvPath, '/public_html') === 0) {
                $data['path'] = '/domains/' . $domain . $srvPath;
            } elseif (strpos($srvPath, 'public_html') === 0) {
                $data['path'] = '/domains/' . $domain . '/' . $srvPath;
            }
        }

        $host = $config['ftp_host'];
        $port = $config['da_port'] ?? '1111';
        $user = !empty($config['da_user']) ? $config['da_user'] : $config['ftp_user'];
        $pass = $config['ftp_pass'];

        $schemes = ['https', 'http'];
        $res = null;
        $lastError = '';

        foreach ($schemes as $scheme) {
            $url = "$scheme://$host:$port/" . ltrim($path, '/');
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            // Adjust timeout based on action (upload vs standard)
            $isUpload = false;
            if (is_array($data) && isset($data['action']) && $data['action'] === 'upload') {
                $isUpload = true;
            }
            $timeout = $isUpload ? 3600 : 90; // 1 hour for uploads, 90s for others
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

            if ($isPost) {
                curl_setopt($ch, CURLOPT_POST, 1);
                $hasFile = false;
                if (is_array($data)) {
                    foreach ($data as $v) {
                        if ($v instanceof \CURLFile) {
                            $hasFile = true;
                            break;
                        }
                    }
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $hasFile ? $data : (is_array($data) ? http_build_query($data) : $data));
            }

            $res = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($res === false) {
                $lastError = $error;
                // If SSL/TLS error, retry with HTTP immediately
                if (strpos($error, 'wrong version number') !== false || strpos($error, 'error:0A00010B') !== false || strpos($error, 'TLS') !== false) {
                    continue;
                }
                continue;
            }

            if ($httpCode >= 400) {
                $lastError = "HTTP Error $httpCode";
                // If HTTPS gave a bad response (maybe port doesn't support SSL), try HTTP
                continue;
            }

            return $res;
        }

        return json_encode(['status' => 'error', 'message' => "DirectAdmin connection failed: $lastError", 'body' => $res]);
    }

    public static function makeDirViaDA($config, $name, $parentPath)
    {
        $data = [
            'action' => 'mkdir',
            'path' => $parentPath,
            'name' => $name
        ];
        $res = self::executeDA($config, 'CMD_API_FILE_MANAGER', $data, true);

        // After mkdir, force chmod 755 to ensure FTP access
        $chmodData = [
            'action' => 'chmod',
            'path' => $parentPath,
            'file1' => $name,
            'chmod' => '755'
        ];
        self::executeDA($config, 'CMD_API_FILE_MANAGER', $chmodData, true);

        return $res;
    }

    public static function deleteViaDA($config, $path, $file)
    {
        $data = [
            'action' => 'delete',
            'path' => $path,
            'select0' => $file
        ];
        return self::executeDA($config, 'CMD_API_FILE_MANAGER', $data, true);
    }

    public static function uploadViaDA($config, $localFile, $remoteDir)
    {
        if (!file_exists($localFile)) return "Local file not found: $localFile";

        $postData = [
            'action' => 'upload',
            'path' => $remoteDir,
            'file1' => new \CURLFile($localFile, 'text/plain', basename($localFile))
        ];

        return self::executeDA($config, 'CMD_API_FILE_MANAGER', $postData, true);
    }

    public static function requestSSLViaDA($config)
    {
        $domain = $config['web_domain'];
        if (empty($domain)) return "Web domain is missing in config.";

        // Step 1: Force SSL and Symlink
        self::executeDA($config, 'CMD_API_DOMAIN', [
            'action' => 'modify',
            'domain' => $domain,
            'ssl' => 'ON',
            'u_base' => 'ON'
        ], true);

        // Step 2: Request Let's Encrypt
        $postData = [
            'domain' => $domain,
            'action' => 'save',
            'type' => 'create',
            'request' => 'letsencrypt',
            'name' => 'Web Administrator',
            'email' => "admin@$domain",
            'city' => 'Hanoi',
            'province' => 'Hanoi',
            'country' => 'VN',
            'company' => 'Nasani',
            'division' => 'IT',
            'keysize' => '2048',
            'submit' => 'Save',
            'le_names0' => $domain,
            'le_select0' => $domain,
            'le_names1' => "www.$domain",
            'le_select1' => "www.$domain",
            'json' => 'yes'
        ];

        return self::executeDA($config, 'CMD_API_SSL', $postData, true);
    }

    public static function changePhpVersionViaDA($config, $phpVersionIndex)
    {
        $domain = $config['web_domain'];
        if (empty($domain)) return "Web domain is missing in config.";

        $cleanHost = str_replace(['https://', 'http://', '/'], '', $domain);
        $cleanHost = explode('/', $cleanHost)[0];

        $postData = [
            'action' => 'php_selector',
            'domain' => $cleanHost,
            'php1_select' => $phpVersionIndex,
            'json' => 'yes'
        ];

        return self::executeDA($config, 'CMD_DOMAIN', $postData, true);
    }

    public static function getAvailablePhpVersionsViaDA($config)
    {
        $domain = $config['web_domain'];
        if (empty($domain)) {
            return ['status' => 'error', 'message' => 'Web domain is missing in config.'];
        }

        $cleanHost = str_replace(['https://', 'http://', '/'], '', $domain);
        $cleanHost = explode('/', $cleanHost)[0];

        $res = self::executeDA($config, "CMD_ADDITIONAL_DOMAINS?action=view&domain=$cleanHost", null, false);

        if (empty($res) || stripos($res, 'DirectAdmin Login') !== false || (is_string($res) && strpos($res, '"status":"error"') !== false)) {
            return ['status' => 'error', 'message' => 'Không thể kết nối DirectAdmin hoặc sai tài khoản/mật khẩu.'];
        }

        // Parse php1_select options
        if (preg_match('/<select[^>]+name=["\']?php1_select["\']?[^>]*>(.*?)<\/select\s*>/is', $res, $selectMatches)) {
            $optionsHtml = $selectMatches[1];
            preg_match_all('/<option\s+[^>]*value=["\']?(\d+)["\']?[^>]*>(.*?)<\/option\s*>/is', $optionsHtml, $optionMatches, PREG_SET_ORDER);
            
            $versions = [];
            foreach ($optionMatches as $match) {
                $value = $match[1];
                $label = trim(strip_tags($match[2]));
                $selected = (stripos($match[0], 'selected') !== false);
                
                $versions[] = [
                    'index' => $value,
                    'version' => $label,
                    'active' => $selected
                ];
            }
            
            if (!empty($versions)) {
                return [
                    'status' => 'success',
                    'data' => $versions
                ];
            }
        }

        return [
            'status' => 'error',
            'message' => 'Không tìm thấy bộ chọn phiên bản PHP của DirectAdmin trên trang này. Có thể tài khoản không được cấp quyền đổi PHP hoặc giao diện DirectAdmin đã thay đổi.'
        ];
    }

    public static function makeDirViaFTP($config, $dirPath)
    {
        $relativeDir = ltrim(rtrim($dirPath, '/'), '/');
        
        // URL must end with a slash for cURL to treat it as a directory
        $url = "ftp://{$config['ftp_host']}/" . $relativeDir . "/";
        $userPwd = "{$config['ftp_user']}:{$config['ftp_pass']}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, $userPwd);
        curl_setopt($ch, CURLOPT_FTP_CREATE_MISSING_DIRS, 2);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        if (defined('CURLOPT_USE_SSL')) curl_setopt($ch, CURLOPT_USE_SSL, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($res === false) {
            return json_encode([
                'status' => 'error',
                'message' => "FTP Mkdir Error: " . $error . " (HTTP Code: $httpCode)"
            ]);
        }
        return json_encode(['status' => 'success']);
    }
}
