<?php

defined('ABSPATH') || exit('NO Access');

/**
 * Kavenegar «verify/lookup» (pattern) SMS. $message holds the pattern values
 * (token, token2, token3) and $code the template name.
 */
class TKM_Kavenegar extends TKM_SMS
{
    public function send()
    {
        if (empty($this->api) || empty($this->code)) {
            return false;
        }

        $values = array_values(array_filter((array) $this->message, function ($v) {
            return $v !== null && $v !== '';
        }));

        $args = [
            'receptor' => $this->phone,
            'template' => $this->code,
        ];
        $keys = ['token', 'token2', 'token3'];
        foreach (array_slice($values, 0, 3) as $i => $value) {
            // Kavenegar tokens cannot contain spaces.
            $args[$keys[$i]] = str_replace(' ', '‌', mb_substr(wp_strip_all_tags((string) $value), 0, 100));
        }

        $url = 'https://api.kavenegar.com/v1/' . rawurlencode($this->api) . '/verify/lookup.json';
        $response = wp_remote_post($url, ['timeout' => 10, 'body' => $args]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }
}
