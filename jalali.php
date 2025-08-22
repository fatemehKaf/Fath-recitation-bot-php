<?php
// jalali.php — Minimal Gregorian -> Jalali formatter (UTF-8)

function _jalali_en2fa_digits($s) {
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    return strtr($s, ['0'=>$fa[0],'1'=>$fa[1],'2'=>$fa[2],'3'=>$fa[3],'4'=>$fa[4],'5'=>$fa[5],'6'=>$fa[6],'7'=>$fa[7],'8'=>$fa[8],'9'=>$fa[9]]);
}

// Gregorian to Jalali (returns [jy, jm, jd])
function _g2j($gy, $gm, $gd){
    $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + floor(($gy2 + 3) / 4) - floor(($gy2 + 99) / 100) + floor(($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * floor($days / 12053));
    $days %= 12053;
    $jy += 4 * floor($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += floor(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + floor($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + floor(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

// نام روز/ماه فارسی
function _jalali_day_name($w){
    $names = ['یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه','شنبه'];
    // در PHP: 0=یکشنبه ... 6=شنبه
    return $names[$w] ?? '';
}
function _jalali_month_name($jm){
    $names = [1=>'فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    return $names[$jm] ?? '';
}

/**
 * فرمت تاریخ شمسی
 * @param int|null $timestamp  یونیکس تایم‌استمپ (اگر null باشد، now)
 * @param string $pattern      الگو شبیه Y/m/d (l برای نام روز، F برای نام ماه)
 * @param bool $persianDigits  ارقام فارسی؟
 * نمونه‌ها: format_jalali_date(null,'Y/m/d (l)'), format_jalali_date(time(),'Y F d، H:i')
 */
function format_jalali_date($timestamp = null, $pattern = 'Y/m/d', $persianDigits = true) {
    if ($timestamp === null) $timestamp = time();
    $gy = (int)date('Y', $timestamp);
    $gm = (int)date('n', $timestamp);
    $gd = (int)date('j', $timestamp);
    [$jy,$jm,$jd] = _g2j($gy,$gm,$gd);

    // نگاشت ساده‌ی pattern‌ها
    $map = [
        'Y' => (string)$jy,
        'y' => substr((string)$jy, -2),
        'm' => str_pad((string)$jm, 2, '0', STR_PAD_LEFT),
        'n' => (string)$jm,
        'd' => str_pad((string)$jd, 2, '0', STR_PAD_LEFT),
        'j' => (string)$jd,
        'F' => _jalali_month_name($jm),
        'l' => _jalali_day_name((int)date('w', $timestamp)), // نام روز از میلادی می‌آد
        // ساعت/دقیقه/ثانیه به‌صورت میلادی (تغییری نمی‌خواهد)
        'H' => date('H', $timestamp),
        'i' => date('i', $timestamp),
        's' => date('s', $timestamp),
    ];

    // جایگزینی کاراکترهای تک‌حرفی استاندارد
    $out = '';
    $len = mb_strlen($pattern,'UTF-8');
    for ($i=0; $i<$len; $i++) {
        $ch = mb_substr($pattern,$i,1,'UTF-8');
        $out .= array_key_exists($ch, $map) ? $map[$ch] : $ch;
    }

    return $persianDigits ? _jalali_en2fa_digits($out) : $out;
}
