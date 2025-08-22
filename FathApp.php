<?php
/**
 * FathApp.php
 * - آیه‌ها از verses.json خوانده می‌شود (UTF-8, بدون BOM).
 * - وضعیت در data.json ذخیره می‌شود.
 * - قفل سبک با data.lock برای جلوگیری از Race Condition.
 * - رفتار: ترتیبی + ختم جمعی + آمار روزانه/کل/شخصی.
 */

class FathApp {
    private $dataFile;
    private $lockFile;
    private $versesFile;
    private $verses = [];

    public function __construct($dataFile = null, $lockFile = null, $versesFile = null) {
        $base = __DIR__;
        $this->dataFile   = $dataFile   ?: $base . '/data.json';
        $this->lockFile   = $lockFile   ?: $base . '/data.lock';
        $this->versesFile = $versesFile ?: $base . '/verses.json';
        $this->verses     = $this->loadVersesFromJson();
        if (count($this->verses) < 1) {
            throw new RuntimeException('Verses list is empty.');
        }
    }

    /** تاریخ امروز (yyyy-mm-dd) */
    public function getTodayDate() {
        return date('Y-m-d');
    }

    /** خواندن آیه‌ها از verses.json */
    private function loadVersesFromJson() {
        if (!file_exists($this->versesFile)) {
            throw new RuntimeException('verses.json not found: '.$this->versesFile);
        }
        $raw = file_get_contents($this->versesFile);

        // حذف BOM اگر وجود داشته باشد
        if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
            $raw = substr($raw, 3);
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            throw new RuntimeException('Invalid verses.json format.');
        }
        // اعتبارسنجی پایه
        foreach ($json as $i => $v) {
            if (!isset($v['arabic'], $v['persian'], $v['english'])) {
                throw new RuntimeException("Invalid verse structure at index ".($i+1));
            }
        }
        return $json;
    }

    /** داده‌های پیش‌فرض */
    public function getDefaultData() {
        return [
            'currentVerse'     => 0,         // ایندکس ۰-مبنـا
            'totalCompletions' => 0,
            'totalVerses'      => 0,
            'userVerses'       => [],        // chatId => count
            'lastUpdate'       => date('c'),
            'dailyStats'       => [
                'date'         => $this->getTodayDate(),
                'completions'  => 0,  // تعداد ختم‌های امروز
                'verses'       => 0,  // تعداد آیات امروز
            ],
            'userCount'         => 0,  // تعداد کل کاربران
        ];
    }

    /** خواندن وضعیت از data.json */
    public function loadData() {
        if (file_exists($this->dataFile)) {
            $raw = file_get_contents($this->dataFile);
            $data = json_decode($raw, true);
            
            // چک کردن داده‌های موجود
            if (is_array($data)) {
                // اطمینان از وجود userVerses
                if (!isset($data['userVerses'])) {
                    $data['userVerses'] = [];
                }
                return $data;
            }
        }
        
        // اگر داده‌ها وجود نداشت، داده‌های پیش‌فرض را برمی‌گردانیم
        return $this->getDefaultData();
    }

    /** ذخیره وضعیت در data.json */
    public function saveData($data) {
        $data['lastUpdate'] = date('c');
    
        // محاسبه تعداد کاربران
        $data['userCount'] = count($data['userVerses']);  // تعداد کاربران از تعداد chatId ها
    
        // آپدیت آمار روزانه
        $today = $this->getTodayDate();
        if ($data['dailyStats']['date'] !== $today) {
            // اگر تاریخ جدید باشد، آمار روزانه را صفر کنیم
            $data['dailyStats'] = [
                'date' => $today,
                'completions' => 0,  // تعداد ختم‌های امروز
                'verses' => 0        // تعداد آیات امروز
            ];
        }
    
        return file_put_contents($this->dataFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }


    /** همگام‌سازی آمار روزانه */
    public function updateDailyStats(&$data) {
        $today = $this->getTodayDate();
        if (!isset($data['dailyStats']) || ($data['dailyStats']['date'] ?? '') !== $today) {
            $data['dailyStats'] = [
                'date'        => $today,
                'completions' => 0,
                'verses'      => 0,
            ];
        }
    }

    /** قفل سادهٔ فایل */
    public function lockFileSystem() {
        $tries = 0;
        while (file_exists($this->lockFile) && $tries < 50) {
            usleep(100000); // 100ms
            $tries++;
        }
        if ($tries >= 50) {
            throw new RuntimeException('Cannot acquire lock.');
        }
        file_put_contents($this->lockFile, getmypid());
    }

    /** آزاد کردن قفل */
    public function unlockFileSystem() {
        if (file_exists($this->lockFile)) {
            @unlink($this->lockFile);
        }
    }

    /**
     * آیهٔ بعدی (ترتیبی/ختم جمعی) + بروزرسانی آمار
     * @param string|int|null $chatId
     * @return array نتیجه شامل آیه و آمار و پرچم completion
     */
    public function getNextVerse($chatId = null) {
        try {
            $this->lockFileSystem();
    
            $data = $this->loadData();
            $this->updateDailyStats($data);  // به‌روزرسانی آمار روزانه
    
            // اطمینان از وجود ساختار کامل
            if (!isset($data['userVerses'])) $data['userVerses'] = [];
            if (!isset($data['totalVerses'])) $data['totalVerses'] = 0;
    
            // آیه فعلی
            $currentVerse = $this->verses[$data['currentVerse']];
            $currentVerseNumber = $data['currentVerse'] + 1;
    
            // آپدیت داده‌ها
            $data['currentVerse']++;
            $data['totalVerses']++;
            $data['dailyStats']['verses']++;  // آیات امروز
    
            // آپدیت آیات کاربر
            if ($chatId) {
                if (!isset($data['userVerses'][$chatId])) {
                    // اگر کاربر جدید است، مقداردهی به آن
                    $data['userVerses'][$chatId] = 1;
                } else {
                    // اگر کاربر قبلاً آیه خوانده، تعداد آیاتش را افزایش می‌دهیم
                    $data['userVerses'][$chatId]++;
                }
            }
    
            $isCompleted = false;
            $completionMessage = '';
    
            // بررسی ختم
            if ($data['currentVerse'] >= count($this->verses)) {
                $data['currentVerse'] = 0;
                $data['totalCompletions']++;
                $data['dailyStats']['completions']++;  // ختم‌های امروز
                $isCompleted = true;
                $completionMessage = "🎉 ختم شماره {$data['totalCompletions']} تکمیل شد!";
            }
    
            // محاسبه تعداد کاربران
            $data['userCount'] = count($data['userVerses']);  // تعداد کاربران از تعداد chatId ها
    
            // ذخیره داده‌ها
            $this->saveData($data);
            $this->unlockFileSystem();
    
            return [
                'success' => true,
                'verse' => [
                    'number' => $currentVerseNumber,
                    'arabic' => $currentVerse['arabic'],
                    'persian' => $currentVerse['persian'],
                    'english' => $currentVerse['english']
                ],
                'stats' => [
                    'totalCompletions' => $data['totalCompletions'],
                    'totalVerses' => $data['totalVerses'],
                    'userVerses' => $chatId ? ($data['userVerses'][$chatId] ?? 0) : 0,
                    'nextVerse' => $data['currentVerse'] + 1,
                    'progress' => round(($data['currentVerse'] / count($this->verses)) * 100),
                    'daily' => $data['dailyStats'],
                    'userCount' => $data['userCount']  // تعداد کاربران
                ],
                'completed' => $isCompleted,
                'completionMessage' => $completionMessage
            ];
    
        } catch (Exception $e) {
            $this->unlockFileSystem();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // تابع جدید برای ارسال پیام در ساعت خاص (9 شب)
    function sendScheduledMessage($chatId) {
        // بررسی تعداد کاربران
        // مقدار واقعی تعداد کاربران را از داده‌های ذخیره‌شده می‌خوانیم
        $data = $this->loadData();
        $userCount = isset($data['userCount']) ? $data['userCount'] : (isset($data['userVerses']) ? count($data['userVerses']) : 0);
        
        // فقط زمانی که تعداد کاربران کمتر از 100 نفر است، پیام ارسال می‌شود
        if ($userCount < 100) {
            // زمان فعلی را دریافت می‌کنیم
            $currentHour = date('H'); // ساعت فعلی را دریافت می‌کند (0 تا 23)
            
            // بررسی می‌کنیم که آیا زمان فعلی 9 شب است یا نه
            if ($currentHour == 21) {
                // ارسال پیام
                $message = "سلام! وقت تلاوت رسیده است.";
                sendMessage($chatId, $message, mainKeyboard());
            }
        } else {
            // در صورتیکه تعداد کاربران بیش از 100 باشد
            echo "تعداد کاربران بیش از 100 نفر است.";
        }
    }


    /** آمار بدون حرکت آیه */
    public function getStats($chatId = null) {
        // بارگذاری داده‌ها از فایل
        $data = $this->loadData();
        
        // به‌روزرسانی آمار روزانه
        $this->updateDailyStats($data);
    
        // تعداد کل آیات (برای سوره فتح 29 آیه)
        $N = count($this->verses);
        
        // آیه فعلی که کاربر در حال خواندن آن است
        $cur = $data['currentVerse'] ?? 0;
        
        // بررسی برای اطمینان از اینکه آیه فعلی در بازه مناسب است
        if ($cur < 0) $cur = 0; 
        if ($cur >= $N) $cur = 0;
    
        // بازگشت آمار به صورت یک آرایه
        return [
            'totalCompletions' => $data['totalCompletions'] ?? 0,  // تعداد ختم‌های کامل
            'totalVerses'      => $data['totalVerses'] ?? 0,      // تعداد آیات خوانده‌شده
            'userVerses'       => $chatId ? ($data['userVerses'][(string)$chatId] ?? 0) : 0,  // تعداد آیات خوانده‌شده توسط کاربر خاص (در صورت وارد بودن chatId)
            'currentVerse'     => $cur + 1,  // آیه فعلی (1-based index)
            'progress'         => round(($cur / $N) * 100),  // درصد پیشرفت در ختم
            'daily'            => $data['dailyStats'],  // آمار روزانه
            'userCount'        => $data['userCount'],  // تعداد کاربران
        ];
    }

    /** فرمت پیام آیه */
    public function formatMessage($result) {
        if (!$result['success']) {
            return "❌ خطا: " . htmlspecialchars($result['error'], ENT_QUOTES, 'UTF-8');
        }

        $ar = htmlspecialchars($result['verse']['arabic'],  ENT_QUOTES, 'UTF-8');
        $fa = htmlspecialchars($result['verse']['persian'], ENT_QUOTES, 'UTF-8');
        $en = htmlspecialchars($result['verse']['english'], ENT_QUOTES, 'UTF-8');

        $message  = "📖 آیه {$result['verse']['number']}\n\n";
        $message .= "{$ar}\n\n";
        $message .= "📚 ترجمه فارسی:\n{$fa}\n\n";
        $message .= "🌍 English Translation:\n{$en}\n\n";
        $message .= "👤 آیات قرائت‌شده توسط شما: {$result['stats']['userVerses']}\n\n";

        if (!empty($result['completed'])) {
            $message .= "{$result['completionMessage']}\n";
            $message .= "🔄 ختم جدید شروع شد!\n\n";
        }

        return $message;
    }
}
