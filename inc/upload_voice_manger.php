<?php

defined('ABSPATH') || exit('NO Access');

class TKM_Upload_Voice
{
    const MAX_BYTES = 10485760; // 10MB ≈ several minutes of webm/opus

    public $voice;

    public function __construct($voice)
    {
        $this->voice = $voice;
    }

    public function upload()
    {
        if (!is_string($this->voice) || !preg_match('#^data:audio/[\w.+-]+(;codecs=[\w.,-]+)?;base64,#i', $this->voice)) {
            return ['success' => false, 'message' => 'فرمت فایل صوتی معتبر نیست'];
        }

        preg_match('#^data:audio/([\w.+-]+)#i', $this->voice, $mime);
        $extensions = ['webm' => 'webm', 'ogg' => 'ogg', 'mp4' => 'm4a', 'x-m4a' => 'm4a', 'aac' => 'aac', 'mpeg' => 'mp3', 'wav' => 'wav'];
        $extension = isset($mime[1], $extensions[strtolower($mime[1])]) ? $extensions[strtolower($mime[1])] : 'webm';

        $decoded_audio = base64_decode(preg_replace('#^data:audio/[^,]+,#i', '', $this->voice), true);

        if ($decoded_audio === false || strlen($decoded_audio) === 0) {
            return ['success' => false, 'message' => 'فایل صوتی خالی است'];
        }

        if (strlen($decoded_audio) > self::MAX_BYTES) {
            return ['success' => false, 'message' => 'حجم پیام صوتی بیش از حد مجاز است'];
        }

        add_filter('upload_dir', [$this, 'custom_upload_dir']);
        $upload_dir = wp_upload_dir();
        remove_filter('upload_dir', [$this, 'custom_upload_dir']);

        if (!empty($upload_dir['error'])) {
            return ['success' => false, 'message' => 'خطا در ذخیره‌سازی ویس'];
        }

        $file_name = wp_unique_filename($upload_dir['path'], 'voice_' . time() . '_' . wp_generate_password(6, false) . '.' . $extension);
        $file_path = trailingslashit($upload_dir['path']) . $file_name;

        if (file_put_contents($file_path, $decoded_audio) === false) {
            return ['success' => false, 'message' => 'خطا در ذخیره‌سازی ویس'];
        }

        return ['success' => true, 'url' => trailingslashit($upload_dir['url']) . $file_name];
    }

    public function custom_upload_dir($args)
    {
        $year = date("Y", time());
        $month = date("m", time());
        $custom_dir = '/tkm-voice-uploads' . '/' . $year . '/' . $month;
        $args['subdir'] = $custom_dir;
        $args['path'] = $args['basedir'] . $custom_dir;
        $args['url'] = $args['baseurl'] . $custom_dir;

        return $args;
    }
}
