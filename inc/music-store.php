<?php
/**
 * 主题设置（theme:yishun）里网易云相关字段的读写，music.php 和 music-login.php 共用。
 * 单独拆出来是为了能在命令行里直接测，不用走后台会话。
 */

if (!function_exists('music_theme_cfg')) {
    function music_theme_cfg($db)
    {
        $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', 'theme:yishun'));
        $cfg = $row ? json_decode($row['value'], true) : [];

        return is_array($cfg) ? $cfg : [];
    }
}

if (!function_exists('music_theme_save')) {
    function music_theme_save($db, array $cfg)
    {
        $value = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $exists = $db->fetchRow($db->select('name')->from('table.options')->where('name = ?', 'theme:yishun'));

        if ($exists) {
            $db->query($db->update('table.options')->rows(['value' => $value])->where('name = ?', 'theme:yishun'));
        } else {
            $db->query($db->insert('table.options')->rows([
                'name' => 'theme:yishun',
                'user' => 0,
                'value' => $value,
            ]));
        }

        return true;
    }
}
