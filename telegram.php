<?php
/**
 * FathBot — Webhook + Reply Keyboard — uses FathApp
 * PHP 7.4+ https://api.telegram.org/bot7626954984:AAELq3Lac1ax3jePOKDCCA2JE27iO33glKk/setWebhook?url=https://fa8dc3918f71.ngrok-free.app/Fath-recitation-bot-php/telegram.php
 
 * انو فقط مونده و کران
 */

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__.'/jalali.php';

// بارگذاری کتابخانه dotenv
require_once 'vendor/autoload.php';

// بارگذاری متغیرهای محیطی از فایل .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// خواندن توکن تلگرام از متغیر محیطی
$Token = $_ENV['TELEGRAM_BOT_TOKEN'];


// حالا می‌توانید متغیرها را از env بخوانید
define('API', 'https://api.telegram.org/bot'.$Token.'/');

// ── Files ──
define('REM_FILE', __DIR__.'/reminders.json');     // برای یادآوری‌های روزانه
define('AWAIT_FILE', __DIR__.'/.awaiting.json');   // حالت «منتظرِ ساعت»

// ── include app ──
require_once __DIR__.'/FathApp.php';
$app = new FathApp();

// ── Telegram API ──
function tg_call($method, $params = []) {
    $ch = curl_init(API.$method);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($params, JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res ? json_decode($res, true) : null;
}
function sendMessage($chat_id, $text, $replyMarkup = null) {
    $p = [
        'chat_id' => $chat_id,
        'text'    => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];
    if ($replyMarkup) $p['reply_markup'] = $replyMarkup;
    return tg_call('sendMessage', $p);
}

// ── Reply Keyboard ──
function mainKeyboard() {
    return [
        'keyboard' => [
            [ ['text'=>'📖 نمایش آیه'], ['text'=>'📊 آمار کلی'] ],
            [ ['text'=>'❓ راهنما']],
        ],
        'resize_keyboard'   => true,
        'one_time_keyboard' => false,
        'is_persistent'     => true,
    ];
}

// ── Reminders (simple JSON + cron) ──
// function loadReminders()     { return file_exists(REM_FILE) ? (json_decode(@file_get_contents(REM_FILE), true) ?: []) : []; }
// function saveReminders($arr) { @file_put_contents(REM_FILE, json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); }
// function getReminderStatus($chatId) {
//     $r = loadReminders(); return $r[(string)$chatId] ?? null;
// }
// function setReminder($chatId, $hours, $minutes) {
//     $r = loadReminders();
//     $r[(string)$chatId] = ['hours'=>(int)$hours,'minutes'=>(int)$minutes,'active'=>true,'setDate'=>date('c'),'last_sent'=>null];
//     saveReminders($r);
// }
// function disableReminder($chatId) {
//     $r = loadReminders();
//     if (isset($r[(string)$chatId])) {
//         $r[(string)$chatId]['active'] = false; saveReminders($r); return true;
//     }
//     return false;
// }
// function isDisableCommand($text) {
//     $t = mb_strtolower(trim($text), 'UTF-8');
//     return in_array($t, ['خاموش','خاموش کن','غیرفعال','off','disable'], true);
// }
// function runReminderCron() {
//     $r = loadReminders(); if (!$r) return ['sent'=>0];
//     $now = new DateTime('now'); $H=(int)$now->format('H'); $M=(int)$now->format('i'); $today=$now->format('Y-m-d');
//     $sent=0;
//     foreach ($r as $chatId=>&$row) {
//         if (!($row['active'] ?? false)) continue;
//         if ((int)$row['hours']===$H && (int)$row['minutes']===$M && ($row['last_sent'] ?? '') !== $today) {
//             $txt = "🔔 <b>یادآوری ختم سوره فتح</b>\n\n"
//                  . "🌹 وقت تلاوت رسیده!\n"
//                  . "📖 برای دریافت آیه، «📖 نمایش آیه» را بزنید.";
//             sendMessage($chatId, $txt, mainKeyboard());
//             $row['last_sent'] = $today; $sent++;
//         }
//     }
//     unset($row); saveReminders($r); return ['sent'=>$sent];
// }

// ── Messages ──
function welcomeMessage($name) {
    $u = htmlspecialchars($name ?? 'کاربر', ENT_QUOTES, 'UTF-8');
    return "🌹 سلام {$u} عزیز!\n\n"
         . "به بات ختم جمعی سوره فتح خوش آمدید\n\n"
         . "🎯 با این بات می‌توانید:\n"
         . "• 📖 در ختم جمعی سوره فتح شرکت کنید\n"
         . "• 📊 آمار کلی و روزانه ختم‌ها را مشاهده کنید  \n"
         . "• ⏰ یادآوری روزانه تنظیم کنید\n\n"
         . "💪 به امید فتح و پیروزی";
}
function helpMessage() {
    return "🌹 <b>راهنمای بات ختم سوره فتح</b>\n\n"
         . "📖 <b>نمایش آیه</b>:\n"
         . "هر بار کلیک کنید، آیه بعدی از سوره فتح دریافت می‌کنید.\n\n"
         . "📊 <b>آمار کلی</b>:\n"
         . "نمایش آمار کل ختم‌های کامل شده توسط همه کاربران.\n\n"
         . "⏰ <b>تنظیم یادآوری</b>:\n"
         . "تنظیم یادآوری روزانه برای تلاوت آیه.\n\n"
         . "🎯 <b>هدف</b>:\n"
         . "شرکت در ختم جمعی سوره مبارک فتح و کسب برکات آن.\n\n"
         . "💪 <i>به امید فتح و پیروزی</i>";
}


function statsMessageFromApp(array $s) {
    $todayFancy = format_jalali_date(null, 'Y/m/d (l)'); // مثل: ۱۴۰۴/۰۵/۲۵ (جمعه)
    return "📊 <b>آمار تفصیلی ختم سوره فتح</b>\n\n"
         . "════════════════════════\n"
         . "📅 <b>تاریخ امروز</b>: {$todayFancy}\n"
         . "🌹 <b>آیه فعلی</b>: {$s['currentVerse']} از 29\n"
         . "📈 <b>پیشرفت</b>: <b>{$s['progress']}%</b>\n\n"
         . "🏆 <b>آمار کلی</b>:\n"
         . "✅ <b>ختم‌های کامل</b>: <b>{$s['totalCompletions']}</b>\n"
         . "📚 <b>کل آیات خوانده‌شده</b>: <b>{$s['totalVerses']}</b>\n"
         . "📅 <b>آمار امروز</b>:\n"
         . "🔄 <b>ختم‌های امروز</b>: <b>{$s['daily']['completions']}</b>\n"
         . "📖 <b>آیات امروز</b>: <b>{$s['daily']['verses']}</b>\n\n"
         . "👤 <b>آمار شخصی شما</b>:\n"
         . "📖 <b>آیات خوانده‌شده</b>: <b>{$s['userVerses']}</b>\n\n"
         . "👥 <b>تعداد کل کاربران</b>: <b>{$s['userCount']}</b>\n\n"
         . "\n"
         . "🎯 <b>بات ختم جمعی سوره فتح</b> را به دوستانتان معرفی کنید تا با هم در این عمل خیر سهیم شویم.\n"
         . "🤲 <i>برای سلامتی و تعجیل در فرج امام زمان صلوات</i>";
}

// ── Router: cron + webhook ──
if (isset($_GET['action']) && $_GET['action']==='cron') {
    $out = runReminderCron();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>true,'result'=>$out], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

// Webhook
header('Content-Type: application/json; charset=utf-8');
$raw = file_get_contents('php://input');
$update = json_decode($raw, true);
if (!$update) { echo json_encode(['ok'=>true]); exit; }

try {
    if (isset($update['message'])) {
        $msg    = $update['message'];
        $chatId = $msg['chat']['id'];
        $text   = trim($msg['text'] ?? '');
        $first  = $msg['from']['first_name'] ?? 'کاربر';

        // دستورات
        if ($text === '/start') {
            sendMessage($chatId, welcomeMessage($first), mainKeyboard());
            echo json_encode(['ok'=>true]); exit;
        }
        if ($text === '/stats') {
            $s = $app->getStats($chatId);
            sendMessage($chatId, statsMessageFromApp($s), mainKeyboard());
            echo json_encode(['ok'=>true]); exit;
        }

        // // حالت انتظارِ ساعت یادآوری؟
        // $aw = file_exists(AWAIT_FILE) ? (json_decode(@file_get_contents(AWAIT_FILE), true) ?: []) : [];
        // $awaiting = $aw[(string)$chatId] ?? null;

        // if ($awaiting === 'reminder_time') {
        //     if (isDisableCommand($text)) {
        //         $ok = disableReminder($chatId);
        //         sendMessage($chatId, $ok ? "❌ یادآوری خاموش شد." : "⚠️ یادآوری فعالی پیدا نشد.", mainKeyboard());
        //         unset($aw[(string)$chatId]); @file_put_contents(AWAIT_FILE, json_encode($aw, JSON_UNESCAPED_UNICODE));
        //         echo json_encode(['ok'=>true]); exit;
        //     }
        //     if (preg_match('/^([0-1]?[0-9]|2[0-3]):([0-5][0-9])$/', $text, $m)) {
        //         setReminder($chatId, (int)$m[1], (int)$m[2]);
        //         $hh = str_pad((string)$m[1], 2, '0', STR_PAD_LEFT);
        //         $mm = str_pad((string)$m[2], 2, '0', STR_PAD_LEFT);
        //         sendMessage($chatId, "✅ یادآوری تنظیم شد: <b>{$hh}:{$mm}</b>", mainKeyboard());
        //         unset($aw[(string)$chatId]); @file_put_contents(AWAIT_FILE, json_encode($aw, JSON_UNESCAPED_UNICODE));
        //         echo json_encode(['ok'=>true]); exit;
        //     } else {
        //         sendMessage($chatId, "❌ <b>فرمت ساعت اشتباه است</b>\n\n"
        //         . "لطفاً ساعت را به فرمت <code>HH:MM</code> وارد کنید. مثال: <code>08:30</code>\n\n"
        //         . "⚠️ جهت انصراف یا  خاموش کردن یادآوری، کافی‌ست «خاموش» را ارسال کنید.", mainKeyboard());
        //                     echo json_encode(['ok'=>true]); exit;
        //     }
        // }

        // دکمه‌های Reply
        $map = [
            '📖 نمایش آیه'    => 'show_verse',
            '📊 آمار کلی'     => 'stats_total',
            '❓ راهنما'        => 'help',
            // '⏰ تنظیم یادآوری' => 'reminder',
        ];
        if (isset($map[$text])) {
            switch ($map[$text]) {
                case 'show_verse':
                    $res = $app->getNextVerse($chatId);   // ← ترتیبی + ختم جمعی
                    $msgText = $app->formatMessage($res);
                    sendMessage($chatId, $msgText, mainKeyboard());
                    break;
                case 'stats_total':
                    $s = $app->getStats($chatId);
                    sendMessage($chatId, statsMessageFromApp($s), mainKeyboard());
                    break;
                case 'help':
                    sendMessage($chatId, helpMessage(), mainKeyboard());
                    break;
                case 'reminder':
                    $cur = getReminderStatus($chatId);
                    $t = "⏰ <b>تنظیم یادآوری روزانه</b>\n\n";
                    if ($cur && !empty($cur['active'])) {
                        $hh = str_pad((string)$cur['hours'], 2, '0', STR_PAD_LEFT);
                        $mm = str_pad((string)$cur['minutes'], 2, '0', STR_PAD_LEFT);
                        $t .= "📅 یادآوری فعلی: <b>{$hh}:{$mm}</b>\n\n";
                    }
                    $t .= "لطفاً ساعت را به‌صورت <code>HH:MM</code> ارسال کنید (مثال: 08:30)\n"
                        . "برای خاموش کردن: «خاموش»";
                    sendMessage($chatId, $t, mainKeyboard());
                    // ورود به حالت انتظار
                    $aw[(string)$chatId] = 'reminder_time';
                    @file_put_contents(AWAIT_FILE, json_encode($aw, JSON_UNESCAPED_UNICODE));
                    break;
            }
        } else {
            sendMessage($chatId, "🌹 لطفاً از دکمه‌های زیر استفاده کنید:", mainKeyboard());
        }
    }
} catch (Throwable $e) {
    // برای سادگی لاگی نمی‌گیریم
}

echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
