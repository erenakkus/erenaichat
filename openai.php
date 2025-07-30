<?php
// openai.php - OpenAI API entegrasyonu

// OpenAI API'ye mesaj gönderme fonksiyonu
function sendMessage($message, $conversation_history = []) {
    global $config;
    
    $api_key = $config['openai_api_key'];
    $model = $config['openai_model'];
    $max_tokens = $config['openai_max_tokens'];
    $temperature = $config['openai_temperature'];
    
    // API isteği için mesaj formatı
    $messages = [];
    
    // Sistem mesajı
    $messages[] = [
        "role" => "system",
        "content" => "Sen yararlı bir yapay zeka asistanısın. Adın EREN AI. Kullanıcı hangi dilde istiyorsa o dilde cevap verirsin ve her konuda uzmansın ince eleyip sık dokuyarak cevap ver özellikle kod yazma ve projelerde."
    ];
    
    // Sohbet geçmişini ekle (son 10 mesaj)
    $recent_history = array_slice($conversation_history, -10);
    foreach ($recent_history as $msg) {
        $role = $msg['is_user'] ? "user" : "assistant";
        $messages[] = [
            "role" => $role,
            "content" => $msg['content']
        ];
    }
    
    // Dosya analizi için ek bilgi ekle
    if (isset($msg['attachment_id']) && !empty($msg['attachment_id'])) {
        $attachment = getAttachmentById($msg['attachment_id']);
        if ($attachment) {
            // Dosya içeriğini oku (metin dosyaları için)
            $content = '';
            $file_extension = pathinfo($attachment['file_name'], PATHINFO_EXTENSION);
            
            // Text, CSV, veya kod dosyalarını okuma
            if (in_array(strtolower($file_extension), ['txt', 'csv', 'php', 'js', 'html', 'css', 'json', 'md'])) {
                $content = file_get_contents($attachment['file_path']);
                
                // Dosya içeriği ekleme
                $messages[] = [
                    "role" => "user",
                    "content" => "Aşağıdaki dosya içeriğini analiz et:\n\n$content"
                ];
            } else {
                // Desteklenmeyen dosya türleri için
                $messages[] = [
                    "role" => "user",
                    "content" => "Bu bir '{$attachment['file_name']}' dosyası. Dosya içeriğini analiz edemiyorum çünkü bu bir metin dosyası değil."
                ];
            }
        }
    }
    
    // En son kullanıcı mesajını ekle (eğer sohbet geçmişinde yoksa)
    if (!empty($message) && (empty($recent_history) || $recent_history[count($recent_history) - 1]['is_user'] == 0)) {
        $messages[] = [
            "role" => "user",
            "content" => $message
        ];
    }
    
    // API isteği için data
    $data = [
        "model" => $model,
        "messages" => $messages,
        "max_tokens" => $max_tokens,
        "temperature" => $temperature
    ];
    
    // cURL isteği
    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $api_key
    ]);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        return [
            "error" => "cURL Error: " . curl_error($ch)
        ];
    }
    
    curl_close($ch);
    
    $response_data = json_decode($response, true);
    
    if (isset($response_data['error'])) {
        return [
            "error" => "API Error: " . $response_data['error']['message']
        ];
    }
    
    if (isset($response_data['choices'][0]['message']['content'])) {
        return [
            "response" => $response_data['choices'][0]['message']['content']
        ];
    }
    
    return [
        "error" => "Unknown API Response"
    ];
}