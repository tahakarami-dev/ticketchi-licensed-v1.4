<?php

namespace Ticketchi\Kb;

use Ticketchi\Support\Settings;

defined('ABSPATH') || exit('NO Access');

/**
 * Help-center articles (custom post type) + public search used for
 * suggestions while a customer writes a ticket.
 */
final class KnowledgeBase
{
    const TYPE = 'tkm_article';
    const TAX = 'tkm_article_cat';

    public static function register()
    {
        add_action('init', [__CLASS__, 'types']);
        add_shortcode('ticketchi_kb', [__CLASS__, 'shortcode']);
        add_filter('the_content', [__CLASS__, 'article_footer']);
    }

    public static function enabled()
    {
        return Settings::bool('kb_enable', true);
    }

    public static function types()
    {
        $slug = sanitize_title(Settings::get('kb_slug', 'help')) ?: 'help';

        register_post_type(self::TYPE, [
            'labels' => [
                'name'          => 'پایگاه دانش',
                'singular_name' => 'مقاله راهنما',
                'add_new'       => 'مقاله جدید',
                'add_new_item'  => 'افزودن مقاله راهنما',
                'edit_item'     => 'ویرایش مقاله',
                'all_items'     => 'همه مقاله‌ها',
                'search_items'  => 'جستجوی مقاله',
                'menu_name'     => 'پایگاه دانش',
            ],
            'public'        => true,
            'show_in_rest'  => true,
            'has_archive'   => false,
            'menu_icon'     => 'dashicons-book-alt',
            'menu_position' => 58,
            'supports'      => ['title', 'editor', 'excerpt', 'revisions'],
            'rewrite'       => ['slug' => $slug, 'with_front' => false],
            'capability_type' => 'post',
        ]);

        register_taxonomy(self::TAX, self::TYPE, [
            'labels' => [
                'name'          => 'دسته‌های راهنما',
                'singular_name' => 'دسته راهنما',
                'add_new_item'  => 'افزودن دسته',
            ],
            'public'            => true,
            'hierarchical'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => ['slug' => $slug . '-category', 'with_front' => false],
        ]);

        // The article slug changed in settings: rebuild permalinks once.
        if (get_option('tkm_flush_rewrite')) {
            delete_option('tkm_flush_rewrite');
            flush_rewrite_rules(false);
        }
    }

    /** @return array[] {id,title,excerpt,url,category} */
    public static function search($query, $limit = 5)
    {
        $query = trim(wp_strip_all_tags((string) $query));
        if (mb_strlen($query) < 2) {
            return [];
        }

        $posts = get_posts([
            'post_type'      => self::TYPE,
            'post_status'    => 'publish',
            's'              => $query,
            'posts_per_page' => $limit,
            'orderby'        => 'relevance',
        ]);

        // WordPress ANDs every word; a customer's sentence rarely matches that way.
        // Fall back to scoring articles by how many meaningful words they contain.
        if (!$posts) {
            $stop = ['برای', 'این', 'که', 'با', 'از', 'به', 'در', 'را', 'های', 'هم', 'یک', 'من', 'است', 'مشکل', 'سلام', 'لطفا', 'دارم', 'نمی', 'شود', 'کنم'];
            $words = array_values(array_unique(array_filter(preg_split('/[\s\x{200c}،,.؟?!]+/u', $query), function ($w) use ($stop) {
                return mb_strlen($w) >= 3 && !in_array($w, $stop, true);
            })));
            $scores = [];
            $found = [];
            foreach (array_slice($words, 0, 6) as $word) {
                foreach (get_posts(['post_type' => self::TYPE, 'post_status' => 'publish', 's' => $word, 'posts_per_page' => 10]) as $post) {
                    $scores[$post->ID] = ($scores[$post->ID] ?? 0) + (mb_stripos($post->post_title, $word) !== false ? 2 : 1);
                    $found[$post->ID] = $post;
                }
            }
            arsort($scores);
            $posts = array_map(function ($id) use ($found) {
                return $found[$id];
            }, array_slice(array_keys($scores), 0, $limit));
        }

        return array_map([__CLASS__, 'card'], $posts);
    }

    public static function card($post)
    {
        $terms = get_the_terms($post, self::TAX);
        return [
            'id'       => $post->ID,
            'title'    => get_the_title($post),
            'excerpt'  => wp_trim_words($post->post_excerpt ?: wp_strip_all_tags($post->post_content), 22, '…'),
            'url'      => get_permalink($post),
            'category' => $terms && !is_wp_error($terms) ? $terms[0]->name : '',
        ];
    }

    /** [ticketchi_kb] — searchable help center. */
    public static function shortcode()
    {
        \TKM_ASSETS::enqueue_front();
        $q = isset($_GET['kb']) ? sanitize_text_field(wp_unslash($_GET['kb'])) : '';

        $cats = get_terms(['taxonomy' => self::TAX, 'hide_empty' => true, 'parent' => 0]);
        $results = $q !== '' ? self::search($q, 20) : [];

        ob_start();
        include TKM_VIEWS_PATH . 'front/kb.php';
        return ob_get_clean();
    }

    /** «Still need help?» link under each article. */
    public static function article_footer($content)
    {
        if (!is_singular(self::TYPE) || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        $url = \Ticketchi\Support\Links::customer_list();
        $url = add_query_arg('action', 'new', $url);
        return $content . '<div style="margin-top:32px;padding:18px 20px;border-radius:14px;background:#f6f7f9;border:1px solid #e7e9ee;font-size:15px">جواب سوالتان را پیدا نکردید؟ <a href="' . esc_url($url) . '" style="font-weight:bold">یک تیکت پشتیبانی ثبت کنید</a></div>';
    }
}
