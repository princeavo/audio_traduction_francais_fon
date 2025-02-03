<?php


$host = $_SERVER['HTTP_HOST'];

# Generate API KEY, see: https://docs.speechflow.io/#/?id=generate-api-key
$API_KEY_ID = "HETBeOL6MjmJLMaK";
$API_KEY_SECRET = "QUXFvyi30Q1XlNLr";
# The language code of the speech in media file.
# See more lang code: https://docs.speechflow.io/#/?id=ap-lang-list
$LANG = "fr";

# The local path or remote path of media file.
$FILE_PATH = "https://sf-docs-prod.s3.us-west-1.amazonaws.com/web/sample-audios/EN.wav";

# The translation result type.
# 1, the default result type, the json format for sentences and words with begin time and end time.
# 2, the json format for the generated subtitles with begin time and end time.
# 3, the srt format for the generated subtitles with begin time and end time.
# 4, the plain text format for transcription results without begin time and end time.
$RESULT_TYPE = 1;
$headers = array("keyId: $API_KEY_ID", "keySecret: $API_KEY_SECRET");
// main("./uploads/audio_67851778b25337.48159248.wav",$LANG,$headers,$RESULT_TYPE);


// Vérifier si un fichier a été envoyé
if (isset($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
    // Nom et chemin du fichier
    $uploadDir = 'uploads/';
    $fileName = uniqid('audio_', true) . '.wav'; // Nom unique pour éviter les conflits
    $filePath = $uploadDir . $fileName;

    // Créer le dossier d'upload s'il n'existe pas
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Déplacer le fichier temporaire vers le dossier cible
    if (move_uploaded_file($_FILES['audio']['tmp_name'], $filePath)) {
        // Réponse de succès
        // echo json_encode([
        //     'status' => 'success',
        //     'message' => 'Fichier audio téléchargé avec succès.',
        //     'file_path' => $filePath,
        // ]);
        try {
            main($filePath,$LANG,$headers,$RESULT_TYPE);
        } catch (Exception $th) {
            echo json_encode([
                'status' => 'error',
                'message' => $th->getMessage(),
            ]);
        }
    } else {
        // Réponse en cas d'échec du déplacement
        echo json_encode([
            'status' => 'error',
            'message' => 'Erreur lors du téléchargement du fichier.',
        ]);
    }
} else {
    // Réponse en cas d'absence de fichier ou d'erreur
    echo json_encode([
        'status' => 'error',
        'message' => 'Aucun fichier reçu ou erreur lors de l\'upload.',
    ]);
}


die();


function create($file_path,$LANG,$headers) {

    $create_data = array(
        "lang" => $LANG
    );

    $create_url = "https://api.speechflow.io/asr/file/v1/create";

    if (strpos($file_path, 'http') === 0) {
        $create_data['remotePath'] = $file_path;
        // echo 'submitting a remote file' . PHP_EOL;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $create_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($create_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
    } else {
        // echo 'submitting a local file' . PHP_EOL;
        $create_url .= "?lang=" . $LANG;

        $create_data = array(
            "file" => new CURLFile($file_path, "audio/wav", "file.wav")
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $create_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $create_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, array("Content-Type: multipart/form-data")));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  FALSE);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
    }

    if ($http_code == 200) {
        $create_result = json_decode($response, true);
        // echo print_r($create_result, true) . PHP_EOL;

        if ($create_result["code"] == 10000) {
            $task_id = $create_result["taskId"];
        } else {
            // echo "create error:" . PHP_EOL;
            // echo $create_result["msg"] . PHP_EOL;
            $task_id = "";
        }
    } else {
        // echo 'create request failed: ' . $http_code . PHP_EOL;
        $task_id = "";
    }

    return $task_id;
}

function query($task_id,$RESULT_TYPE,$headers) {

    $query_url = "https://api.speechflow.io/asr/file/v1/query?taskId=" . $task_id . "&resultType=" . strval($RESULT_TYPE);
    // echo 'querying transcription result' . PHP_EOL;

    while (true) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $query_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($http_code == 200) {
            $query_result = json_decode($response, true);

            if ($query_result["code"] == 11000) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Fichier audio téléchargé avec succès.',
                    'data' => $query_result,
                ]);
                // echo 'transcription result:' . PHP_EOL;
                // echo print_r($query_result, true) . PHP_EOL;
                break;
            } elseif ($query_result["code"] == 11001) {
                // echo 'waiting' . PHP_EOL;
                sleep(3);
                continue;
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Erreur de transcription :' . $query_result['msg'],
                ]);
                // echo print_r($query_result, true) . PHP_EOL;
                // echo "transcription error:" . PHP_EOL;
                // echo $query_result['msg'] . PHP_EOL;
                break;
            }
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'requête échouée: ' . $http_code . PHP_EOL,
            ]);
            break;
        }
    }
}

function main($file_path,$lang,$headers,$RESULT_TYPE) {
    $task_id = create($file_path,$lang,$headers);

    if ($task_id != "") {
        query($task_id,$RESULT_TYPE,$headers);
    }else{
        echo json_encode([
            'status' => 'error',
            'message' => 'Aucun fichier reçu ou erreur lors de l\'upload.',
        ]);
    }
}


?>