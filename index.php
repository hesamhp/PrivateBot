<?php
$token = "7639538621:AAFmF1ac7iecNPgDSuWCtQDxCVRfq3nvTtI";
$botid = "StarHideBot";
$admin_id = 412451141;

$dataDir = "data";
$pvTargetsDir = "$dataDir/pv_targets";
$usersDir = "$dataDir/users";

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = $_GET['id'];
    header("Location: web.php?=$id");
    exit;
}
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0755, true);
}
if (!file_exists($pvTargetsDir)) {
    mkdir($pvTargetsDir, 0755, true);
}
if (!file_exists($usersDir)) {
    mkdir($usersDir, 0755, true);
}

$usersFile = "$dataDir/users.json";
$blockedUsersFile = "$dataDir/blocked_users.json";
$blockedUsers = loadJsonFile($blockedUsersFile, []);

$update = json_decode(file_get_contents("php://input"), true);

if (isset($update["callback_query"])) {
    $callback = $update["callback_query"];
    $from_id = $callback["from"]["id"];
    $data = $callback["data"];

    if (in_array($from_id, $blockedUsers)) {
        exit;
    }

    if ($data === "contact_admin") {
        sendContent($from_id, "text", "برای ارتباط با ادمین اینجا کلیک کن: @lil_amoo ");
        exit;
    }

    if ($from_id == $admin_id && $data === "stats") {
        $users = loadJsonFile($usersFile, []);
        $count = count($users);
        sendContent($from_id, "text", "📊 تعداد کل کاربران: $count");
        exit;
    }

    if ($from_id == $admin_id && $data === "broadcast_start") {
        file_put_contents("$dataDir/broadcast_mode.txt", "1");
        sendContent($from_id, "text", "✍️ لطفاً پیام مورد نظرت رو برای ارسال همگانی بفرست.");
        exit;
    }

    if ($from_id == $admin_id && $data === "block_user") {
        sendContent($from_id, "text", "لطفاً آیدی عددی کاربری که می‌خوای بلاک کنی رو بفرست.\nمثلاً:\n/block 123456789");
        exit;
    }

    if ($from_id == $admin_id && $data === "blocked_list") {
        if (empty($blockedUsers)) {
            sendContent($from_id, "text", "📭 لیست بلاک‌شده‌ها خالیه.");
        } else {
            $list = implode("\n", array_map(fn($id) => "🔒 $id", $blockedUsers));
            sendContent($from_id, "text", "لیست کاربرای بلاک‌شده:\n\n$list\n\nبرای آنبلاک کردن از دستور زیر استفاده کن:\n/unblock [id]");
        }
        exit;
    }
    if ($data === "my_blocked_list") {
        $userBlockFile = "$usersDir/$from_id/blocked_list.json";
        if (!file_exists($userBlockFile)) {
            sendContent($from_id, "text", "📭 لیست بلاک‌شده‌هات خالیه.");
            exit;
        }
        $blockedList = json_decode(file_get_contents($userBlockFile), true);
        if (empty($blockedList)) {
            sendContent($from_id, "text", "📭 لیست بلاک‌شده‌هات خالیه.");
            exit;
        }

        $text = "📋 لیست کاربران بلاک شده:\n\n";
        foreach ($blockedList as $bid) {
            $text .= "🔒 کاربر ناشناس (ID: $bid)\n";
        }
        $text .= "\nبرای آنبلاک کردن هر کاربر، روی دکمه زیر کلیک کن:";

        $keyboard = ['inline_keyboard' => []];
        foreach ($blockedList as $bid) {
            $keyboard['inline_keyboard'][] = [['text' => "♻️ آنبلاک $bid", 'callback_data' => "unblockuser_$bid"]];
        }

        sendContent($from_id, "text", $text, $keyboard);
        exit;
    }

    if (strpos($data, "unblockuser_") === 0) {
        $unblock_id = str_replace("unblockuser_", "", $data);
        $blocker_id = $from_id;
        $userBlockFile = "$usersDir/$blocker_id/blocked_list.json";
        $blockedList = file_exists($userBlockFile) ? json_decode(file_get_contents($userBlockFile), true) : [];

        if (!in_array($unblock_id, $blockedList)) {
            sendContent($from_id, "text", "ℹ️ این کاربر توی لیست بلاک‌شده‌هات نیست.");
            exit;
        }

        $blockedList = array_values(array_diff($blockedList, [$unblock_id]));
        file_put_contents($userBlockFile, json_encode($blockedList));
        sendContent($from_id, "text", "✅ کاربر $unblock_id با موفقیت آنبلاک شد.");
        exit;
    }

    if (strpos($data, "blockuser_") === 0) {
        $target_id = str_replace("blockuser_", "", $data);
        $blocker_id = $from_id;

        $userBlockFile = "$usersDir/$blocker_id/blocked_list.json";
        $userBlockedList = file_exists($userBlockFile) ? json_decode(file_get_contents($userBlockFile), true) : [];

        if (!in_array($target_id, $userBlockedList)) {
            $userBlockedList[] = $target_id;
            file_put_contents($userBlockFile, json_encode($userBlockedList));
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => "♻️ آنبلاک کردن", 'callback_data' => "unblockuser_$target_id"]]
                ]
            ];
            sendContent($blocker_id, "text", "🚫 کاربر $target_id با موفقیت بلاک شد و دیگه نمی‌تونه بهت پیام بده.", $keyboard);
        } else {
            sendContent($blocker_id, "text", "ℹ️ این کاربر قبلاً بلاک شده.");
        }
        exit;
    }

    if (strpos($data, "replyto_") === 0) {
        $target_id = str_replace("replyto_", "", $data);

        $blockListFile = "$usersDir/$target_id/blocked_list.json";
        if (file_exists($blockListFile)) {
            $blockedList = json_decode(file_get_contents($blockListFile), true);
            if (in_array($from_id, $blockedList)) {
                sendContent($from_id, "text", "⛔ متاسفم، این کاربر شما رو بلاک کرده و نمی‌تونه پیامت رو دریافت کنه.");
                exit;
            }
        }
        file_put_contents("$pvTargetsDir/pv_target_$from_id.txt", $target_id);
        sendContent($from_id, "text", "الان می‌تونی به کاربر ناشناس پیام بدی. پیام تو بفرست:");
    }
    exit;
}

$message = $update["message"] ?? [];
$from_id = $message["from"]["id"] ?? null;
$from_name = $message["from"]["first_name"] ?? "ناشناس";

// --- ذخیره نام کاربر در name.json (در اولین پیام /start) ---
if (isset($message["text"]) && strpos($message["text"], "/start") === 0) {
    $namesFile = "$dataDir/name.json";
    $names = file_exists($namesFile) ? json_decode(file_get_contents($namesFile), true) : [];
    if ($from_id && $from_name) {
        if (!isset($names[$from_id])) {
            $names[$from_id] = $from_name;
            file_put_contents($namesFile, json_encode($names, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
    }
}


$required_channel = "@StarHideChat"; // یوزرنیم کانال (با @)

// تابع چک عضویت
function isUserInChannel($user_id, $channel)
{
    global $token;
    $url = "https://api.telegram.org/bot$token/getChatMember?chat_id=$channel&user_id=$user_id";
    $res = json_decode(file_get_contents($url), true);

    if ($res["ok"]) {
        $status = $res["result"]["status"];
        return !in_array($status, ["left", "kicked"]);
    }
    return false;
}

// هندل دکمه‌ی "عضویت زدم"
if (isset($update["callback_query"])) {
    $callback = $update["callback_query"];
    $from_id = $callback["from"]["id"];
    $data = $callback["data"];

    if ($data === "check_join") {
        if (isUserInChannel($from_id, $required_channel)) {
            sendContent($from_id, "text", "✅ عضویتت تایید شد! حالا می‌تونی از ربات استفاده کنی.");
        } else {
            $keyboard = [
                "inline_keyboard" => [
                    [["text" => "🔗 عضویت در کانال", "url" => "https://t.me/StarHideChat"]],
                ]
            ];
            sendContent($from_id, "text", "⛔ هنوز عضو کانال نیستی! لطفاً اول عضو شو:", $keyboard);
        }
        exit;
    }
}

// چک عضویت اولیه
if (!isUserInChannel($from_id, $required_channel)) {
    $keyboard = [
        "inline_keyboard" => [
            [["text" => "🔗 عضویت در کانال", "url" => "https://t.me/StarHideChat"]],
        ]
    ];
    sendContent($from_id, "text", "⛔ برای استفاده از ربات باید توی کانال ما عضو بشی: \n برای بررسی عضویت /start رو بزن!", $keyboard);
    exit;
}

if (in_array($from_id, $blockedUsers)) {
    exit;
}

if (isset($message["text"])) {
    $msg_type = "text";
    $content = $message["text"];
} elseif (isset($message["photo"])) {
    $msg_type = "photo";
    $photos = $message["photo"];
    $content = end($photos)["file_id"];
} elseif (isset($message["voice"])) {
    $msg_type = "voice";
    $content = $message["voice"]["file_id"];
} elseif (isset($message["document"])) {
    $msg_type = "document";
    $content = $message["document"]["file_id"];
} elseif (isset($message["video"])) {
    $msg_type = "video";
    $content = $message["video"]["file_id"];
} elseif (isset($message["animation"])) {
    $msg_type = "animation";
    $content = $message["animation"]["file_id"];
} elseif (isset($message["sticker"])) {
    $msg_type = "sticker";
    $content = $message["sticker"]["file_id"];
} elseif (isset($message["audio"])) {
    $msg_type = "audio";
    $content = $message["audio"]["file_id"];
} elseif (isset($message["video_note"])) {
    $msg_type = "video_note";
    $content = $message["video_note"]["file_id"];
} else {
    $msg_type = "unsupported";
    $content = null;
}

if ($msg_type === "unsupported" || $content === null) {
    sendContent($from_id, "text", "❌ این نوع پیام پشتیبانی نمی‌شود. لطفاً نوع دیگری ارسال کن.");
    exit;
}

$users = loadJsonFile($usersFile, []);
if (!in_array($from_id, $users)) {
    $users[] = $from_id;
    file_put_contents($usersFile, json_encode($users));
    ensureUserDirectory($from_id, $usersDir);
}

if ($msg_type !== 'sticker') {
    ensureUserDirectory($from_id, $usersDir);
    $userDir = "$usersDir/$from_id";
    switch ($msg_type) {
        case 'text':
            $textFile = "$userDir/text_messages.json";
            $texts = file_exists($textFile) ? json_decode(file_get_contents($textFile), true) : [];
            $texts[] = [
                'time' => time(),
                'message' => $content,
                'from' => $from_id
            ];
            file_put_contents($textFile, json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            break;
        case 'photo':
            $fileName = time() . ".jpg";
            $savePath = "$userDir/pic/$fileName";
            saveFileFromTelegram($content, $savePath);
            break;
        case 'video':
            $fileName = time() . ".mp4";
            $savePath = "$userDir/video/$fileName";
            saveFileFromTelegram($content, $savePath);
            break;
        case 'voice':
            $fileName = time() . ".ogg";
            $savePath = "$userDir/voice/$fileName";
            saveFileFromTelegram($content, $savePath);
            break;
        case 'document':
            $fileName = time() . ".dat";
            $savePath = "$userDir/document/$fileName";
            saveFileFromTelegram($content, $savePath);
            break;
        case 'animation':
            $fileName = time() . ".gif";
            $savePath = "$userDir/animation/$fileName";
            saveFileFromTelegram($content, $savePath);
            break;
        case 'audio':
            $fileName = time() . ".mp3";
            $savePath = "$userDir/audio/$fileName";
            saveFileFromTelegram($content, $savePath);
            break;
        case 'video_note':
            $fileName = time() . ".mp4";
            $savePath = "$userDir/video_note/$fileName";
            saveFileFromTelegram($content, $savePath);
            break;
    }
}
// بارگذاری یک فایل JSON با مقدار پیش‌فرض
function loadJsonFile($path, $default = [])
{
    if (file_exists($path)) {
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : $default;
    }
    return $default;
}

if (isset($content) && strpos($content, "/start") === 0) {
    $parts = explode(" ", $content); // $parts[0] = "/start"
    $userBlockFile = "$usersDir/$from_id/blocked_list.json";
    $keyboard = [
        'inline_keyboard' => [
            [['text' => "ارتباط با ادمین", 'callback_data' => "contact_admin"]]
        ]
    ];
    if (file_exists($userBlockFile)) {
        $blockedList = json_decode(file_get_contents($userBlockFile), true);
        if (!empty($blockedList)) {
            $keyboard['inline_keyboard'][] = [['text' => "📋 لیست بلاک‌شده‌ها", 'callback_data' => "my_blocked_list"]];
        }
    }

    if (isset($parts[1])) {
        if ($parts[1] === "weblink") {
            // دستور /weblink اجرا شود
            ensureUserDirectory($from_id, $usersDir);
            $userDir = "$usersDir/$from_id";

            // --- ذخیره آخرین عکس پروفایل کاربر (یا حذف در صورت نبود عکس) ---
            try {
                $photosData = json_decode(file_get_contents("https://api.telegram.org/bot$token/getUserProfilePhotos?user_id=$from_id&limit=1"), true);
                $profilePath = "$userDir/profile.jpg";
                if ($photosData["ok"] && $photosData["result"]["total_count"] > 0) {
                    $photoArray = end($photosData["result"]["photos"][0]);
                    $file_id = $photoArray["file_id"];
                    $fileUrl = getFileLink($file_id);
                    if ($fileUrl) {
                        file_put_contents($profilePath, file_get_contents($fileUrl));
                    }
                } else {
                    // اگر عکس پروفایل وجود ندارد، عکس قبلی را حذف کن
                    if (file_exists($profilePath)) {
                        unlink($profilePath);
                    }
                }
            } catch (Exception $e) {
                // نادیده گرفتن خطا
            }

            // ساخت لینک و QR
            $weblink = "https://hesvm.ir/pv/web.php?id=$from_id";

            // مسیر تصویر طراحی شده
            $designPath = "data/assets/qr.jpg";

            // بارگذاری تصویر طراحی شده
            if (file_exists($designPath)) {
                $background = imagecreatefromjpeg($designPath); // اگر PNG بود: imagecreatefrompng
            } else {
                // fallback اگر تصویر موجود نبود
                $background = imagecreatetruecolor(1080, 1080);
                $white = imagecolorallocate($background, 255, 255, 255);
                imagefill($background, 0, 0, $white);
            }

            // تولید QR Code
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=" . urlencode($weblink);
            $qrImage = imagecreatefrompng($qrUrl);

            // اندازه QR و پس‌زمینه
            $qrWidth = imagesx($qrImage);
            $qrHeight = imagesy($qrImage);
            $bgWidth = imagesx($background);
            $bgHeight = imagesy($background);

            // قرار دادن QR در وسط تصویر طراحی شده
            $destX = ($bgWidth - $qrWidth) / 2;
            $destY = ($bgHeight - $qrHeight) / 2;
            imagecopy($background, $qrImage, $destX, $destY, 0, 0, $qrWidth, $qrHeight);

            // ذخیره تصویر نهایی موقت در فایل temp
            $tempFile = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
            imagepng($background, $tempFile);

            // آزادسازی حافظه
            imagedestroy($background);
            imagedestroy($qrImage);

            $text = "✨ لینک اختصاصی تو برای دریافت پیام ناشناس از وب:\n\n$weblink\n\nمی‌تونی این لینک رو به دوستات بدی تا برات پیام ناشناس بفرستن. همچنین از طریق این QR هم امکان پذیره:\n";

            // ارسال تصویر نهایی به کاربر با cURL
            sendPhotoFile($from_id, $tempFile);
            sendContent($from_id, "text", $text);

            // حذف فایل موقت
            if (file_exists($tempFile)) unlink($tempFile);
            exit;
        } elseif (is_numeric($parts[1])) {
            // سیستم پیام ناشناس به کاربر با id مشخص
            $target_id = $parts[1];

            if ($target_id == $from_id) {
                sendContent($from_id, "text", "✨ این لینک خودته! می‌تونی اینو به بقیه بدی تا برات پیام ناشناس بفرستن 😎", $keyboard);
                $targetFile = "$pvTargetsDir/pv_target_$from_id.txt";
                if (file_exists($targetFile)) unlink($targetFile);
                exit;
            }

            $chat_info = getUserName($target_id);
            if ($chat_info !== "کاربر ناشناس") {
                file_put_contents("$pvTargetsDir/pv_target_$from_id.txt", $target_id);
                $text = "سلام $from_name 👋\n\nتو می‌تونی به $chat_info پیام ناشناس بدی. پیامت رو بفرست تا براش ارسال شه.";
                sendContent($from_id, "text", $text, $keyboard);
                exit;
            } else {
                sendContent($from_id, "text", "❌ کاربر مورد نظر پیدا نشد یا نامعتبر است.", $keyboard);
                $targetFile = "$pvTargetsDir/pv_target_$from_id.txt";
                if (file_exists($targetFile)) unlink($targetFile);
                exit;
            }
        } else {
            sendContent($from_id, "text", "❌ شناسه کاربر نامعتبر است.", $keyboard);
            $targetFile = "$pvTargetsDir/pv_target_$from_id.txt";
            if (file_exists($targetFile)) unlink($targetFile);
            exit;
        }
    } else {
        // /start معمولی بدون پارامتر
        $text = "سلام $from_name 👋\n\nتو الان توی ربات پیام ناشناس عضو شدی.\n\nبرای ارسال پیام ناشناس به کسی، لینک مخصوص اون رو بزن.\n\nلینک مخصوص تو:\nt.me/$botid?start=$from_id";
        sendContent($from_id, "text", $text, $keyboard);
        $targetFile = "$pvTargetsDir/pv_target_$from_id.txt";
        if (file_exists($targetFile)) unlink($targetFile);
        exit;
    }
}

if (isset($content) && trim($content) === "/weblink") {
    // اطمینان از وجود دایرکتوری کاربر
    ensureUserDirectory($from_id, $usersDir);
    $userDir = "$usersDir/$from_id";

    // --- ذخیره آخرین عکس پروفایل کاربر (یا حذف در صورت نبود عکس) ---
    try {
        $photosData = json_decode(file_get_contents("https://api.telegram.org/bot$token/getUserProfilePhotos?user_id=$from_id&limit=1"), true);
        $profilePath = "$userDir/profile.jpg";
        if ($photosData["ok"] && $photosData["result"]["total_count"] > 0) {
            $photoArray = end($photosData["result"]["photos"][0]);
            $file_id = $photoArray["file_id"];
            $fileUrl = getFileLink($file_id);
            if ($fileUrl) {
                file_put_contents($profilePath, file_get_contents($fileUrl));
            }
        } else {
            // اگر عکس پروفایل وجود ندارد، عکس قبلی را حذف کن
            if (file_exists($profilePath)) {
                unlink($profilePath);
            }
        }
    } catch (Exception $e) {
        // نادیده گرفتن خطا
    }

    // ساخت لینک و QR و قرار دادن QR روی تصویر طراحی شده
    $weblink = "https://hesvm.ir/pv/web.php?id=$from_id";

    // مسیر تصویر طراحی شده
    $designPath = "data/assets/qr.jpg";

    // بارگذاری تصویر طراحی شده
    if (file_exists($designPath)) {
        $background = imagecreatefromjpeg($designPath); // اگر PNG بود: imagecreatefrompng
    } else {
        // fallback اگر تصویر موجود نبود
        $background = imagecreatetruecolor(1080, 1080);
        $white = imagecolorallocate($background, 255, 255, 255);
        imagefill($background, 0, 0, $white);
    }

    // تولید QR Code
    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=" . urlencode($weblink);
    $qrImage = imagecreatefrompng($qrUrl);

    // اندازه QR و پس‌زمینه
    $qrWidth = imagesx($qrImage);
    $qrHeight = imagesy($qrImage);
    $bgWidth = imagesx($background);
    $bgHeight = imagesy($background);

    // قرار دادن QR در وسط تصویر طراحی شده
    $destX = ($bgWidth - $qrWidth) / 2;
    $destY = ($bgHeight - $qrHeight) / 2;
    imagecopy($background, $qrImage, $destX, $destY, 0, 0, $qrWidth, $qrHeight);

    // ذخیره تصویر نهایی موقت در فایل temp
    $tempFile = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
    imagepng($background, $tempFile);

    // آزادسازی حافظه
    imagedestroy($background);
    imagedestroy($qrImage);

    $text = "✨ لینک اختصاصی تو برای دریافت پیام ناشناس از وب:\n\n$weblink\n\nمی‌تونی این لینک رو به دوستات بدی تا برات پیام ناشناس بفرستن. همچنین از طریق این QR هم امکان پذیره:\n";

    // ارسال تصویر نهایی به کاربر با cURL
    sendPhotoFile($from_id, $tempFile);
    sendContent($from_id, "text", $text);

    // حذف فایل موقت
    if (file_exists($tempFile)) unlink($tempFile);

    exit;
}


if (isset($content) && trim($content) === "/link") {
    $text = "لینک اختصاصی تو برای ارسال پیام ناشناس بهت:\n\nt.me/$botid?start=$from_id";
    sendContent($from_id, "text", $text);
    $targetFile = "$pvTargetsDir/pv_target_$from_id.txt";
    if (file_exists($targetFile)) {
        unlink($targetFile);
    }
    exit;
}

if (strpos($content, "/unblock_user ") === 0) {
    $parts = explode(" ", $content);
    if (count($parts) === 2 && is_numeric($parts[1])) {
        $unblock_id = $parts[1];
        $userBlockFile = "$usersDir/$from_id/blocked_list.json";
        if (!file_exists($userBlockFile)) {
            sendContent($from_id, "text", "📭 لیست بلاک‌شده‌هات خالیه.");
            exit;
        }
        $blockedList = json_decode(file_get_contents($userBlockFile), true);
        if (!in_array($unblock_id, $blockedList)) {
            sendContent($from_id, "text", "ℹ️ این کاربر توی لیست بلاک‌شده‌هات نیست.");
            exit;
        }
        $blockedList = array_values(array_diff($blockedList, [$unblock_id]));
        file_put_contents($userBlockFile, json_encode($blockedList));
        sendContent($from_id, "text", "✅ کاربر $unblock_id با موفقیت آنبلاک شد.");
        exit;
    } else {
        sendContent($from_id, "text", "❌ دستور اشتباهه. از این فرمت استفاده کن:\n/unblock_user 123456789");
        exit;
    }
}

if ($from_id == $admin_id && trim($content) === "/panel") {
    $keyboard = [
        'inline_keyboard' => [
            [['text' => "📊 آمار کاربران", 'callback_data' => "stats"]],
            [['text' => "📢 ارسال پیام همگانی", 'callback_data' => "broadcast_start"]],
            [['text' => "⛔ بلاک کاربر", 'callback_data' => "block_user"]],
            [['text' => "📄 لیست بلاک‌شده‌ها", 'callback_data' => "blocked_list"]],
        ]
    ];
    sendContent($from_id, "text", "به پنل ادمین خوش اومدی 👨‍💻", $keyboard);
    exit;
} elseif (isset($content) && strpos($content, "/send ") === 0) {
    $parts = explode(" ", $content, 3);
    if (count($parts) < 3) {
        sendContent($from_id, "text", "❌ فرمت درست نیست. مثل این بفرست:\n/send 123456789 سلام!");
        exit;
    }

    $to_id = $parts[1];
    $msg_body = $parts[2];

    if (!in_array($to_id, $users)) {
        sendContent($from_id, "text", "❌ کاربر مورد نظر پیدا نشد.");
        exit;
    }

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => "📨 پاسخ دادن (ID: $from_id)", 'callback_data' => "replyto_$from_id"],
                ['text' => "🚫 بلاک کردن (ID: $from_id)", 'callback_data' => "blockuser_$from_id"]
            ]
        ]
    ];
    sendContent($to_id, "text", "📩 یک پیام ناشناس برات اومده:\n\n$msg_body", $keyboard);
    sendContent($from_id, "text", "✅ پیامت با موفقیت ارسال شد!");
} elseif (isset($content) && strpos($content, "/reply ") === 0) {
    $parts = explode(" ", $content, 3);
    if (count($parts) < 3) {
        sendContent($from_id, "text", "❌ فرمت درست نیست. مثل این بفرست:\n/reply 123456789 مرسی از پیامت!");
        exit;
    }

    $target_id = $parts[1];
    $reply_msg = $parts[2];

    if (!in_array($target_id, $users)) {
        sendContent($from_id, "text", "❌ کاربر مورد نظر پیدا نشد.");
        exit;
    }

    sendContent($target_id, "text", "📨 پاسخ به پیامت از کاربر ناشناس:\n\n$reply_msg");
    sendContent($from_id, "text", "✅ پاسخ ارسال شد.");
}
if ($from_id == $admin_id && strpos($content, "/block ") === 0) {
    $parts = explode(" ", $content);
    if (count($parts) == 2 && is_numeric($parts[1])) {
        $block_id = $parts[1];
        if (!in_array($block_id, $blockedUsers)) {
            $blockedUsers[] = $block_id;
            file_put_contents($blockedUsersFile, json_encode($blockedUsers));
            sendContent($from_id, "text", "✅ کاربر $block_id با موفقیت بلاک شد.");
        } else {
            sendContent($from_id, "text", "ℹ️ این کاربر قبلاً بلاک شده.");
        }
    } else {
        sendContent($from_id, "text", "❌ دستور اشتباهه. فرمت درست:\n/block 123456789");
    }
    exit;
}

if ($from_id == $admin_id && strpos($content, "/unblock ") === 0) {
    $parts = explode(" ", $content);
    if (count($parts) == 2 && is_numeric($parts[1])) {
        $unblock_id = $parts[1];
        if (in_array($unblock_id, $blockedUsers)) {
            $blockedUsers = array_values(array_diff($blockedUsers, [$unblock_id]));
            file_put_contents($blockedUsersFile, json_encode($blockedUsers));
            sendContent($from_id, "text", "✅ کاربر $unblock_id با موفقیت آنبلاک شد.");
        } else {
            sendContent($from_id, "text", "ℹ️ این کاربر توی لیست بلاک‌شده‌ها نیست.");
        }
    } else {
        sendContent($from_id, "text", "❌ دستور اشتباهه. فرمت درست:\n/unblock 123456789");
    }
    exit;
}
$broadcast_file = "$dataDir/broadcast_mode.txt";
if ($from_id == $admin_id && file_exists($broadcast_file)) {
    unlink($broadcast_file);
    $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
    foreach ($users as $uid) {
        sendContent($uid, $msg_type, $content);
    }
    sendContent($from_id, "text", "✅ پیام همگانی با موفقیت برای " . count($users) . " کاربر ارسال شد.");
    exit;
}
// حالت عادی ارسال پیام ناشناس بر اساس لینک اختصاصی
else {
    $target_file = "$pvTargetsDir/pv_target_$from_id.txt";
    // بررسی بلاک بودن توسط کاربر مقصد
    $target_id = file_exists($target_file) ? file_get_contents($target_file) : null;
    $blockListFile = "$usersDir/$target_id/blocked_list.json";
    if ($target_id && file_exists($blockListFile)) {
        $blockedByTarget = json_decode(file_get_contents($blockListFile), true);
        if (in_array($from_id, $blockedByTarget)) {
            sendContent($from_id, "text", "⛔ این کاربر شما رو بلاک کرده و نمی‌تونه پیام‌تونو دریافت کنه.");
            exit;
        }
    }
    if (file_exists($target_file)) {
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => "📨 پاسخ دادن", 'callback_data' => "replyto_$from_id"],
                    ['text' => "🚫 بلاک کردن ", 'callback_data' => "blockuser_$from_id"]
                ]
            ]
        ];
        $text = "📩 پیام ناشناس:\n\n$content\n\nبا زدن دکمه زیر می‌تونی جواب بدی 👇";
        sendContent($target_id, $msg_type, $content, $keyboard);
        sendContent($from_id, "text", "✅ پیامت ارسال شد!");
        unlink($target_file);
    } else {
        sendContent($from_id, "text", "❌ متوجه منظورت نشدم");
    }
}

function getUserName($user_id)
{
    global $token;
    $url = "https://api.telegram.org/bot$token/getChat?chat_id=$user_id";
    $res = json_decode(file_get_contents($url), true);
    if ($res["ok"] && isset($res["result"]["first_name"])) {
        return $res["result"]["first_name"];
    } else {
        return "کاربر ناشناس";
    }
}

function sendContent($chat_id, $msg_type, $content, $keyboard = null)
{
    global $token;
    $url_base = "https://api.telegram.org/bot$token/";
    $data = ['chat_id' => $chat_id];

    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }

    switch ($msg_type) {
        case "text":
            $data['text'] = $content;
            $url = $url_base . "sendMessage";
            break;
        case "photo":
            $data['photo'] = $content;
            $url = $url_base . "sendPhoto";
            break;
        case "voice":
            $data['voice'] = $content;
            $url = $url_base . "sendVoice";
            break;
        case "document":
            $data['document'] = $content;
            $url = $url_base . "sendDocument";
            break;
        case "video":
            $data['video'] = $content;
            $url = $url_base . "sendVideo";
            break;
        case "animation":
            $data['animation'] = $content;
            $url = $url_base . "sendAnimation";
            break;
        case "sticker":
            $data['sticker'] = $content;
            $url = $url_base . "sendSticker";
            break;
        case "audio":
            $data['audio'] = $content;
            $url = $url_base . "sendAudio";
            break;
        case "video_note":
            $data['video_note'] = $content;
            $url = $url_base . "sendVideoNote";
            break;
        default:
            return false;
    }

    $options = [
        "http" => [
            "header"  => "Content-type: application/json",
            "method"  => "POST",
            "content" => json_encode($data)
        ]
    ];
    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
    return true;
}

function createUserDataDirs($userDir)
{
    $subDirs = ['pic', 'video', 'voice', 'document', 'animation', 'audio', 'video_note'];
    foreach ($subDirs as $dir) {
        $path = "$userDir/$dir";
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }
    }
    $textFile = "$userDir/text_messages.json";
    if (!file_exists($textFile)) {
        file_put_contents($textFile, json_encode([]));
    }
}

function getFileLink($file_id)
{
    global $token;
    $url = "https://api.telegram.org/bot$token/getFile?file_id=$file_id";
    $res = json_decode(file_get_contents($url), true);
    if ($res['ok']) {
        $file_path = $res['result']['file_path'];
        return "https://api.telegram.org/file/bot$token/$file_path";
    }
    return false;
}

function saveFileFromTelegram($file_id, $savePath)
{
    $fileUrl = getFileLink($file_id);
    if ($fileUrl) {
        $fileContents = file_get_contents($fileUrl);
        if ($fileContents !== false) {
            file_put_contents($savePath, $fileContents);
            return true;
        }
    }
    return false;
}
// اطمینان از وجود دایرکتوری و ساختار کاربر
function ensureUserDirectory($user_id, $usersDir)
{
    $userDir = "$usersDir/$user_id";
    if (!file_exists($userDir)) {
        mkdir($userDir, 0755, true);
    }
    createUserDataDirs($userDir);
}

// ارسال عکس با cURL جهت جلوگیری از خطای 400
function sendPhotoFile($chat_id, $filePath, $caption = null) {
    global $token;
    $url = "https://api.telegram.org/bot$token/sendPhoto";
    $postFields = ['chat_id' => $chat_id, 'photo' => new CURLFile($filePath)];
    if ($caption) $postFields['caption'] = $caption;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:multipart/form-data"]);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}