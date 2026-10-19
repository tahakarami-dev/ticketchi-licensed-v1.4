<?php

defined('ABSPATH') || exit('NO Access');

class TKM_Analysis
{
   private $wpdb;
   private $tickets;
   private $replies;
   private $ratings;
   private $users;
   private $departments;

   public function __construct()
   {
      global $wpdb;
      $this->wpdb = $wpdb;
      $this->tickets = $wpdb->prefix . 'tkm_tickets';
      $this->replies = $wpdb->prefix . 'tkm_replies';
      $this->ratings = $wpdb->prefix . 'tkm_ratings';
      $this->users = $wpdb->prefix . 'tkm_users';
      $this->departments = $wpdb->prefix . 'tkm_departments';
   }

   /* ───────────── Legacy API (kept, fixed) ───────────── */

   public function count_tickets()
   {
      return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->tickets} WHERE status != 'trash'");
   }

   public function count_replys()
   {
      return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->replies}");
   }

   public function get_average_response_time()
   {
      $seconds = $this->average_first_response(3650);
      return $seconds === null ? 'بدون داده' : self::duration($seconds);
   }

   public function get_average_ticket_rating()
   {
      $result = $this->wpdb->get_var("SELECT AVG(rating) FROM {$this->ratings}");
      return $result === null ? 'بدون امتیاز' : number_format((float) $result, 2);
   }

   public function count_users()
   {
      return (int) $this->wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$this->users}");
   }

   public function get_user_satisfaction_description()
   {
      $average_rating = $this->wpdb->get_var("SELECT AVG(rating) FROM {$this->ratings} WHERE rating IS NOT NULL");
      return self::satisfaction_label($average_rating);
   }

   public static function satisfaction_label($average_rating)
   {
      if ($average_rating === null) {
         return 'نامشخص';
      }
      $average_rating = (float) $average_rating;
      if ($average_rating >= 4.5) {
         return 'عالی';
      } elseif ($average_rating >= 3.5) {
         return 'خوب';
      } elseif ($average_rating >= 2.5) {
         return 'متوسط';
      }
      return 'ناراضی';
   }

   /**
    * Per-agent stats. Agents assigned to a parent department also cover its
    * sub-departments. DISTINCT avoids double counting through the joins.
    */
   public function get_employee_ticket_stats()
   {
      $rows = $this->wpdb->get_results("
         SELECT u.user_id,
            COUNT(DISTINCT t.ID) AS ticket_count,
            COUNT(DISTINCT CASE WHEN t.status = 'open' THEN t.ID END) AS open_ticket_count,
            COUNT(DISTINCT CASE WHEN t.status = 'answerd' THEN t.ID END) AS answered_ticket_count,
            AVG(r.rating) AS avg_rating
         FROM {$this->users} u
         LEFT JOIN {$this->departments} d ON (d.ID = u.department_id OR d.parent = u.department_id)
         LEFT JOIN {$this->tickets} t ON t.department_id = d.ID AND t.status != 'trash'
         LEFT JOIN {$this->ratings} r ON r.ticket_id = t.ID
         GROUP BY u.user_id
         ORDER BY ticket_count DESC
      ");

      $replies = $this->wpdb->get_results("SELECT creator_id, COUNT(*) AS total FROM {$this->replies} WHERE from_admin = 1 GROUP BY creator_id");
      $reply_counts = [];
      foreach ($replies as $row) {
         $reply_counts[(int) $row->creator_id] = (int) $row->total;
      }

      foreach ($rows as $row) {
         $row->reply_count = isset($reply_counts[(int) $row->user_id]) ? $reply_counts[(int) $row->user_id] : 0;
         $row->avg_rating = $row->avg_rating === null ? null : round((float) $row->avg_rating, 2);
      }

      return $rows;
   }

   /* ───────────── Dashboard ───────────── */

   private static function since($days)
   {
      return date('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
   }

   public function count_between($from, $to = null)
   {
      if ($to) {
         return (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$this->tickets} WHERE status != 'trash' AND create_date >= %s AND create_date < %s", $from, $to));
      }
      return (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$this->tickets} WHERE status != 'trash' AND create_date >= %s", $from));
   }

   /**
    * Average seconds between a customer ticket and the first staff reply.
    */
   public function average_first_response($days = 30)
   {
      $rows = $this->wpdb->get_results($this->wpdb->prepare("
         SELECT t.create_date, MIN(r.create_date) AS first_reply
         FROM {$this->tickets} t
         INNER JOIN {$this->replies} r ON r.ticket_id = t.ID AND r.from_admin = 1
         WHERE t.create_date >= %s AND (t.from_admin IS NULL OR t.from_admin = 0)
         GROUP BY t.ID, t.create_date
      ", self::since($days)));

      $total = 0;
      $count = 0;
      foreach ($rows as $row) {
         $diff = strtotime($row->first_reply) - strtotime($row->create_date);
         if ($diff >= 0) {
            $total += $diff;
            $count++;
         }
      }

      return $count ? (int) round($total / $count) : null;
   }

   public static function duration($seconds)
   {
      $seconds = (int) $seconds;
      if ($seconds < 3600) {
         return tkm_fa(max(1, (int) round($seconds / 60))) . ' دقیقه';
      }
      if ($seconds < DAY_IN_SECONDS) {
         $hours = floor($seconds / 3600);
         $minutes = round(($seconds % 3600) / 60);
         return tkm_fa($hours) . ' ساعت' . ($minutes ? ' و ' . tkm_fa($minutes) . ' دقیقه' : '');
      }
      $days = floor($seconds / DAY_IN_SECONDS);
      $hours = round(($seconds % DAY_IN_SECONDS) / 3600);
      return tkm_fa($days) . ' روز' . ($hours ? ' و ' . tkm_fa($hours) . ' ساعت' : '');
   }

   public function kpis($days = 30)
   {
      $now = self::since(0);
      $start = self::since($days);
      $prev = self::since($days * 2);

      $rating = $this->wpdb->get_row("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total FROM {$this->ratings}");

      return [
         'created'          => $this->count_between($start),
         'created_prev'     => $this->count_between($prev, $start),
         'open'             => (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->tickets} WHERE status = 'open'"),
         'answered'         => (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->tickets} WHERE status = 'answerd'"),
         'high_open'        => (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->tickets} WHERE status = 'open' AND priority = 'high'"),
         'first_response'   => $this->average_first_response($days),
         'replies'          => (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$this->replies} WHERE create_date >= %s", $start)),
         'rating'           => $rating && $rating->avg_rating !== null ? round((float) $rating->avg_rating, 1) : null,
         'rating_count'     => $rating ? (int) $rating->total : 0,
         'total'            => $this->count_tickets(),
         'now'              => $now,
      ];
   }

   /**
    * Tickets created per day for the last $days days (site timezone), oldest first.
    */
   public function daily_counts($days = 30)
   {
      $tz = wp_timezone();
      $series = [];
      $today = new DateTimeImmutable('today', $tz);

      for ($i = $days - 1; $i >= 0; $i--) {
         $series[$today->modify("-{$i} days")->format('Y-m-d')] = 0;
      }

      $dates = $this->wpdb->get_col($this->wpdb->prepare("SELECT create_date FROM {$this->tickets} WHERE status != 'trash' AND create_date >= %s", self::since($days + 1)));

      foreach ($dates as $date) {
         $day = (new DateTimeImmutable($date, new DateTimeZone('UTC')))->setTimezone($tz)->format('Y-m-d');
         if (isset($series[$day])) {
            $series[$day]++;
         }
      }

      return $series;
   }

   public function status_counts()
   {
      $rows = $this->wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$this->tickets} WHERE status != 'trash' GROUP BY status");
      $counts = [];
      foreach ($rows as $row) {
         $counts[$row->status] = (int) $row->total;
      }
      return $counts;
   }

   public function priority_counts()
   {
      $rows = $this->wpdb->get_results("SELECT priority, COUNT(*) AS total FROM {$this->tickets} WHERE status NOT IN ('trash', 'closed', 'finish') GROUP BY priority");
      $counts = ['high' => 0, 'medium' => 0, 'low' => 0];
      foreach ($rows as $row) {
         if (isset($counts[$row->priority])) {
            $counts[$row->priority] = (int) $row->total;
         }
      }
      return $counts;
   }

   public function department_counts($limit = 6)
   {
      return $this->wpdb->get_results($this->wpdb->prepare("
         SELECT d.ID, d.name, COUNT(t.ID) AS total,
            SUM(CASE WHEN t.status = 'open' THEN 1 ELSE 0 END) AS open_count
         FROM {$this->departments} d
         INNER JOIN {$this->tickets} t ON t.department_id = d.ID AND t.status != 'trash'
         GROUP BY d.ID, d.name
         ORDER BY total DESC
         LIMIT %d
      ", $limit));
   }

   public function recent_tickets($limit = 6)
   {
      return $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$this->tickets} WHERE status != 'trash' ORDER BY reply_date DESC LIMIT %d", $limit));
   }

   public function rating_distribution()
   {
      $rows = $this->wpdb->get_results("SELECT rating, COUNT(*) AS total FROM {$this->ratings} GROUP BY rating");
      $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
      foreach ($rows as $row) {
         if (isset($dist[(int) $row->rating])) {
            $dist[(int) $row->rating] = (int) $row->total;
         }
      }
      return $dist;
   }
}
