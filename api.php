<?php

// ==========================
// FORCE CORS FIRST
// ==========================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// ==========================
// HANDLE PREFLIGHT
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ==========================
// SHOW ERRORS TEMPORARILY
// ==========================
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ==========================
// LOAD CORE
// ==========================

require "core.php";


// ==========================
// INPUT SAFE PARSER
// ==========================
function getJSON() {

    $raw = file_get_contents("php://input");

    return json_decode($raw, true) ?? [];
}


// ==========================
// ACTION
// ==========================
$action = $_GET['action'] ?? '';


// ==========================
// SIGNUP
// ==========================
if ($action === "signup") {

    $data = getJSON();

    if (
        empty($data['customer_id']) ||
        empty($data['website_name']) ||
        empty($data['email']) ||
        empty($data['business_type'])
    ) {

        echo json_encode([
            "error" => "Missing fields"
        ]);

        exit;
    }

    $res = supabase("POST", "chatbot_signups", [[
        "customer_id" => $data['customer_id'],
        "website_name" => $data['website_name'],
        "email" => $data['email'],
        "business_type" => $data['business_type'],
        "theme_color" => "#007bff"
    ]]);

    echo json_encode([
        "status" => "signup_done",
        "debug" => $res
    ]);

    exit;
}


// ==========================
// UPDATE THEME
// ==========================
if ($action === "update_theme") {

    $data = getJSON();

    if (
        empty($data['customer_id']) ||
        empty($data['theme_color'])
    ) {

        echo json_encode([
            "error" => "Missing data"
        ]);

        exit;
    }

    $res = supabase(
        "PATCH",
        "chatbot_signups?customer_id=eq." . trim($data['customer_id']),
        [
            "theme_color" => $data['theme_color']
        ]
    );

    echo json_encode([
        "status" => "theme_updated",
        "theme_color" => $data['theme_color'],
        "debug" => $res
    ]);

    exit;
}


// ==========================
// GET THEME
// ==========================
if ($action === "get_theme") {

    $customer_id = $_GET['customer_id'] ?? '';

    if (!$customer_id) {

        echo json_encode([
            "error" => "Missing customer_id"
        ]);

        exit;
    }

    $res = supabase(
        "GET",
        "chatbot_signups?select=theme_color&customer_id=eq." . trim($customer_id)
    );

    $color = "#007bff";

    if (!empty($res['data'][0]['theme_color'])) {
        $color = $res['data'][0]['theme_color'];
    }

    echo json_encode([
        "theme_color" => $color
    ]);

    exit;
}


// ==========================
// ADD FAQ
// ==========================
if ($action === "add_faq") {

    $data = getJSON();

    if (
        empty($data['customer_id']) ||
        empty($data['faqs'])
    ) {

        echo json_encode([
            "error" => "Missing FAQ data"
        ]);

        exit;
    }

    $rows = [];

    foreach ($data['faqs'] as $faq) {

        if (
            !empty($faq['question']) &&
            !empty($faq['answer'])
        ) {

            $rows[] = [
                "customer_id" => $data['customer_id'],
                "question" => $faq['question'],
                "answer" => $faq['answer']
            ];
        }
    }

    if (empty($rows)) {

        echo json_encode([
            "error" => "No valid FAQs"
        ]);

        exit;
    }

    $res = supabase(
        "POST",
        "faq_questions",
        $rows
    );

    echo json_encode([
        "status" => "faq_saved",
        "debug" => $res
    ]);

    exit;
}


// ==========================
// CHAT
// ==========================
if ($action === "chat") {

    $data = getJSON();

    $customer_id = $data['customer_id'] ?? $_GET['customer_id'] ?? '';
    $message = $data['message'] ?? '';

    if (
        !$customer_id ||
        !$message
    ) {

        echo json_encode([
            "error" => "Missing customer_id or message"
        ]);

        exit;
    }

    $faqs = supabase(
        "GET",
        "faq_questions?customer_id=eq." . trim($customer_id)
    );

    $faqs = $faqs['data'] ?? [];

    $input = strtolower(trim($message));

    $reply = null;

    foreach ($faqs as $faq) {

        $q = strtolower(trim($faq['question'] ?? ''));

        if (!$q) continue;

        similar_text($input, $q, $percent);

        if (
            strpos($input, $q) !== false ||
            strpos($q, $input) !== false ||
            $percent > 55
        ) {

            $reply = $faq['answer'];
            break;
        }
    }

    if (!$reply) {
        $reply = "Sorry, I don't have an answer for that yet.";
    }

    echo json_encode([
        "reply" => $reply
    ]);

    exit;
}


// ==========================
// TOP FAQS (RANKED)
// ==========================
if ($action === "get_top_faqs") {

    $customer_id = $_GET['customer_id'] ?? '';

    if (!$customer_id) {

        echo json_encode([
            "error" => "Missing customer_id"
        ]);

        exit;
    }

    // ==========================
    // GET FAQ USAGE
    // ==========================
    $usage = supabase(
        "GET",
        "faq_usage?select=question_id&customer_id=eq." . trim($customer_id)
    );

    $usageRows = $usage['data'] ?? [];

    // ==========================
    // COUNT QUESTION USAGE
    // ==========================
    $counts = [];

    foreach ($usageRows as $row) {

        $qid = $row['question_id'] ?? 0;

        if (!$qid) continue;

        if (!isset($counts[$qid])) {
            $counts[$qid] = 0;
        }

        $counts[$qid]++;
    }

    // ==========================
    // SORT MOST USED
    // ==========================
    arsort($counts);

    $topIds = array_slice(array_keys($counts), 0, 5);

    // ==========================
    // FALLBACK IF NO DATA
    // ==========================
    if (empty($topIds)) {

        $res = supabase(
            "GET",
            "faq_questions?select=id,question&customer_id=eq." . trim($customer_id) . "&limit=5"
        );

        echo json_encode([
            "data" => $res['data'] ?? []
        ]);

        exit;
    }

    // ==========================
    // FETCH QUESTIONS
    // ==========================
    $idList = implode(",", $topIds);

    $res = supabase(
        "GET",
        "faq_questions?select=id,question"
        . "&customer_id=eq." . trim($customer_id)
        . "&id=in.(" . $idList . ")"
    );

    $questions = $res['data'] ?? [];

    // ==========================
    // KEEP RANK ORDER
    // ==========================
    usort($questions, function($a, $b) use ($counts) {

        return ($counts[$b['id']] ?? 0)
             - ($counts[$a['id']] ?? 0);
    });

    echo json_encode([
        "data" => $questions
    ]);

    exit;
}


// ==========================
// SEARCH FAQS
// ==========================
if ($action === "search_faqs") {

    $customer_id = $_GET['customer_id'] ?? '';
    $q = $_GET['q'] ?? '';

    if (!$customer_id) {

        echo json_encode([
            "error" => "Missing customer_id"
        ]);

        exit;
    }

    $query =
        "faq_questions?select=id,question"
        . "&customer_id=eq." . trim($customer_id);

    if (!empty($q)) {
        $query .= "&question=ilike.*" . urlencode($q) . "*";
    }

    $res = supabase(
        "GET",
        $query
    );

    echo json_encode([
        "data" => $res['data'] ?? []
    ]);

    exit;
}


// ==========================
// TRACK FAQ USAGE
// ==========================
if ($action === "track_faq_usage") {

    $data = getJSON();

    if (
        empty($data['customer_id']) ||
        empty($data['question_id']) ||
        empty($data['user_id'])
    ) {

        echo json_encode([
            "error" => "Missing data"
        ]);

        exit;
    }

    // ==========================
    // CHECK EXISTING
    // ==========================
    $check = supabase(
        "GET",
        "faq_usage?select=id"
        . "&customer_id=eq." . urlencode(trim($data['customer_id']))
        . "&question_id=eq." . intval($data['question_id'])
        . "&user_id=eq." . urlencode(trim($data['user_id']))
        . "&limit=1"
    );

    // ==========================
    // ALREADY TRACKED
    // ==========================
    if (!empty($check['data'])) {

        echo json_encode([
            "status" => "already_tracked"
        ]);

        exit;
    }

    // ==========================
    // INSERT
    // ==========================
    $res = supabase(
        "POST",
        "faq_usage",
        [[
            "customer_id" => trim($data['customer_id']),
            "question_id" => intval($data['question_id']),
            "user_id" => trim($data['user_id'])
        ]]
    );

    echo json_encode([
        "status" => "tracked",
        "debug" => $res
    ]);

    exit;
}


// ==========================
// CREATE CUSTOMER
// ==========================
if ($action === "create_customer") {

    function generateUUID() {

        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    echo json_encode([
        "customer_id" => generateUUID()
    ]);

    exit;
}


// ==========================
// DEFAULT
// ==========================
echo json_encode([
    "error" => "Invalid action",
    "available" => [
        "signup",
        "update_theme",
        "get_theme",
        "add_faq",
        "chat",
        "get_top_faqs",
        "search_faqs",
        "track_faq_usage",
        "create_customer"
    ]
]);