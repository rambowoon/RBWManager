<?php
namespace RamboWoon;

class ConfigManager {
    private $configPath;

    public function __construct($path) {
        $this->configPath = $path;
    }

    public function getAll() {
        if (!file_exists($this->configPath)) return [];
        return json_decode(file_get_contents($this->configPath), true) ?: [];
    }

    public function save($projectName, $config, $category = null) {
        $configs = $this->getAll();
        
        if ($category !== null && $category !== '') {
            if (!isset($configs[$category])) $configs[$category] = [];
            $configs[$category][$projectName] = $config;
            
            // Migrate flat to hierarchical
            if (isset($configs[$projectName]) && !is_array($configs[$projectName] ?? null)) {
                // Not migrating scalar value, wait, if it's flat it's an array
            }
            if (isset($configs[$projectName]) && !isset($configs[$projectName][$projectName])) {
                 unset($configs[$projectName]);
            }
        } else {
            $found = false;
            foreach ($configs as $cat => &$projects) {
                if (is_array($projects) && isset($projects[$projectName])) {
                    $projects[$projectName] = $config;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $configs[$projectName] = $config;
            }
        }
        return file_put_contents($this->configPath, json_encode($configs, JSON_PRETTY_PRINT));
    }

    public function delete($projectName, $category = null) {
        $configs = $this->getAll();
        
        if ($category !== null && $category !== '') {
            if (isset($configs[$category][$projectName])) {
                unset($configs[$category][$projectName]);
                return file_put_contents($this->configPath, json_encode($configs, JSON_PRETTY_PRINT));
            }
        } else {
            foreach ($configs as $cat => &$projects) {
                if (is_array($projects) && isset($projects[$projectName])) {
                    unset($projects[$projectName]);
                    return file_put_contents($this->configPath, json_encode($configs, JSON_PRETTY_PRINT));
                }
            }
            if (isset($configs[$projectName])) {
                unset($configs[$projectName]);
                return file_put_contents($this->configPath, json_encode($configs, JSON_PRETTY_PRINT));
            }
        }
        return false;
    }

    public function updateDeployedInfo($projectName, $stage, $info, $category = null) {
        $config = $this->getForProject($projectName, $category) ?: [];
        if (!isset($config['deployed'])) $config['deployed'] = [];
        $config['deployed'][$stage] = $info;
        return $this->save($projectName, $config, $category);
    }

    public function addHistory($projectName, $action, $message = '', $category = null) {
        $config = $this->getForProject($projectName, $category) ?: [];
        if (!isset($config['history'])) $config['history'] = [];
        
        $entry = [
            'action' => $action,
            'message' => $message,
            'time' => date('Y-m-d H:i:s')
        ];
        
        array_unshift($config['history'], $entry);
        $config['history'] = array_slice($config['history'], 0, 50);
        
        return $this->save($projectName, $config, $category);
    }

    public function getForProject($projectName, $category = null) {
        $configs = $this->getAll();
        
        if ($category !== null && $category !== '') {
            if (isset($configs[$category][$projectName])) {
                return $configs[$category][$projectName];
            }
        }
        
        // Fallback to flat structure (old format) if it exists at root
        // Ensure it's a project config by checking for common keys like 'demo', 'prod', 'history', 'lock_demo' or 'deployed'
        if (isset($configs[$projectName])) {
            $flat = $configs[$projectName];
            if (is_array($flat) && (isset($flat['demo']) || isset($flat['prod']) || isset($flat['history']) || isset($flat['lock_demo']) || isset($flat['deployed']))) {
                return $flat;
            }
        }

        return [];
    }
}
