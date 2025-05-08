<?php
// Initialize variables
$message = '';
$csvData = [];
$processedData = [];
$semester = '2';
$session = '2024/2025';
$collegeAverage = '9.9';
$unitMappings = [];

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["csvFile"])) {
    $targetDir = "uploads/";
    
    // Create uploads directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    // Get form data
    $semester = isset($_POST['semester']) ? $_POST['semester'] : '2';
    $session = isset($_POST['session']) ? $_POST['session'] : '2023/2024';
    $collegeAverage = isset($_POST['collegeAverage']) ? $_POST['collegeAverage'] : '9.9';
    
    // Process unit mappings if provided
    if (isset($_POST['unitPrefix']) && isset($_POST['unitName']) 
        && is_array($_POST['unitPrefix']) && is_array($_POST['unitName'])) {
        $prefixes = $_POST['unitPrefix'];
        $names = $_POST['unitName'];
        
        for ($i = 0; $i < count($prefixes); $i++) {
            if (!empty($prefixes[$i]) && !empty($names[$i])) {
                $unitMappings[strtoupper(trim($prefixes[$i]))] = trim($names[$i]);
            }
        }
    }
    
    $targetFile = $targetDir . basename($_FILES["csvFile"]["name"]);
    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    
    // Check if file is a CSV
    if ($fileType != "csv") {
        $message = '<div class="alert alert-danger">Sorry, only CSV files are allowed.</div>';
    } else {
        if (move_uploaded_file($_FILES["csvFile"]["tmp_name"], $targetFile)) {
            // Process the CSV file
            $csvData = processCSV($targetFile);
            $processedData = processEvaluationData($csvData, $unitMappings);
            $message = '<div class="alert alert-success">The file has been uploaded and processed.</div>';
        } else {
            $message = '<div class="alert alert-danger">Sorry, there was an error uploading your file.</div>';
        }
    }
}

if (move_uploaded_file($_FILES["csvFile"]["tmp_name"], $targetFile)) {
    // Success code
} else {
    // Log the detailed error information
    $phpFileUploadErrors = [
        0 => 'There is no error, the file uploaded with success',
        1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        3 => 'The uploaded file was only partially uploaded',
        4 => 'No file was uploaded',
        6 => 'Missing a temporary folder',
        7 => 'Failed to write file to disk',
        8 => 'A PHP extension stopped the file upload',
    ];
    
    $errorCode = $_FILES["csvFile"]["error"];
    $errorMessage = isset($phpFileUploadErrors[$errorCode]) ? $phpFileUploadErrors[$errorCode] : 'Unknown upload error';
    
    file_put_contents('upload_error.log', date('Y-m-d H:i:s') . ' - Upload Error: ' . $errorMessage . PHP_EOL, FILE_APPEND);
    $message = '<div class="alert alert-danger">Upload error: ' . $errorMessage . '</div>';
}

// Check if form was submitted and if file was uploaded
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES) && isset($_FILES["csvFile"]) && isset($_FILES["csvFile"]["name"]) && !empty($_FILES["csvFile"]["name"])) {
    
    $targetDir = "uploads/";
    $targetFile = $targetDir . basename($_FILES["csvFile"]["name"]);
    
    // Create uploads directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    // Rest of your file processing code
    
} else if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Form was submitted but no file was selected
    $message = '<div class="alert alert-danger">Please select a CSV file to upload.</div>';
    file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . ' - Form submitted but no file selected' . PHP_EOL, FILE_APPEND);
    
    // Debug what we received
    file_put_contents('debug_log.txt', 'POST data: ' . print_r($_POST, true) . PHP_EOL, FILE_APPEND);
    file_put_contents('debug_log.txt', 'FILES data: ' . print_r($_FILES, true) . PHP_EOL, FILE_APPEND);
}






/**
 * Get the unit/department from the course code
 * @param string $courseCode The course code
 * @param array $customMappings Custom unit mappings
 * @return string The unit/department
 */
function getUnitFromCourseCode($courseCode, $customMappings = []) {
    // Extract the first two characters
    $prefix = strtoupper(substr($courseCode, 0, 2));
    
    // Check if we have a custom mapping for this prefix
    if (!empty($customMappings) && isset($customMappings[$prefix])) {
        return $customMappings[$prefix];
    }
    
    // Map prefix to department
    $departments = [
        'SC' => 'Sains Komputer',
        'DC' => 'Sains Komputer',
        'AA' => 'Perakaunan',
        'AP' => 'Pengurusan Perniagaan',
        'WM' => 'Pendidikan Moral',
        'DM' => 'Matematik',
        'AM' => 'Matematik',
        'SM' => 'Matematik',
        'DB' => 'Biologi',
        'SB' => 'Biologi',
        'DK' => 'Kimia',
        'SK' => 'Kimia',
        'WK' => 'Kokurikulum',
        'WP' => 'Pengajian Am Matrikulasi',
        'WI' => 'Pendidikan Islam',
        'DP' => 'Fizik',
        'SP' => 'Fizik',
        'AE' => 'Ekonomi',
        'WE' => 'Bahasa Inggeris',
        'DE' => 'Bahasa Inggeris',
        // Add more mappings as needed
    ];
    
    return isset($departments[$prefix]) ? $departments[$prefix] : 'Umum';
}

/**
 * Process CSV file and return data as array
 * @param string $filePath Path to CSV file
 * @return array Processed CSV data
 */
function processCSV($filePath) {
    $data = [];
    if (($handle = fopen($filePath, "r")) !== FALSE) {
        // Read header row
        $header = fgetcsv($handle);
        
        // Debug: Log the header to see what columns we have
        error_log("CSV Headers: " . json_encode($header));
        
        // Read data rows
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) === count($header)) {
                $data[] = array_combine($header, $row);
            }
        }
        fclose($handle);
    }
    return $data;
}

/**
 * Process evaluation data to group by teacher with flexible item count
 * @param array $csvData Raw CSV data
 * @param array $customMappings Custom unit mappings
 * @return array Processed data grouped by teacher
 */
function processEvaluationData($csvData, $customMappings = []) {
    $teachers = [];
    
    // First, analyze the first row to determine which questions exist in this dataset
    $availableQuestions = [
        'professionalism' => [],
        'teaching' => [],
        'english' => [] // Initialize as empty array even if no English questions
    ];
    
    if (!empty($csvData)) {
        $firstRow = $csvData[0];
        
        // Check which professionalism items exist
        $profItems = [
            'appearance' => 'Q04_A: Profesionalisme->1. Penampilan pensyarah menarik minat saya untuk belajar.',
            'punctuality' => 'Q04_A: Profesionalisme->2. Pensyarah menepati waktu yang ditetapkan.',
            'care' => 'Q04_A: Profesionalisme->3. Pensyarah mengambil berat terhadap pembelajaran saya.'
        ];
        
        foreach ($profItems as $key => $column) {
            if (isset($firstRow[$column])) {
                $availableQuestions['professionalism'][$key] = $column;
            }
        }
        
        // Check which teaching items exist
        $teachItems = [
            'knowledge' => 'Q05_B: Pengajaran dan Pembelajaran->4. Pensyarah mempunyai pengetahuan yang luas tentang kursus yang diajar.',
            'understanding' => 'Q05_B: Pengajaran dan Pembelajaran->5. Saya faham isi kandungan kursus yang disampaikan oleh pensyarah.',
            'materials' => 'Q05_B: Pengajaran dan Pembelajaran->6. Bahan bantu mengajar pensyarah seperti nota / bahan aktiviti  membantu meningkatkan pemahaman saya.',
            'methods' => 'Q05_B: Pengajaran dan Pembelajaran->7. Kaedah pengajaran pensyarah saya membantu saya memahami isi kandungan kursus.',
            'exercises' => 'Q05_B: Pengajaran dan Pembelajaran->8. Latihan/tugasan/kuiz yang dilaksanakan di dalam kelas oleh pensyarah menguji pemahaman saya.',
            'online_learning' => 'Q05_B: Pengajaran dan Pembelajaran->9. Pembelajaran dalam talian (online) yang dilaksanakan oleh pensyarah membantu proses pembelajaran saya.',
            'online_materials' => 'Q05_B: Pengajaran dan Pembelajaran->10. Bahan PdP dalam talian dapat diakses dengan mudah'
        ];
        
        foreach ($teachItems as $key => $column) {
            if (isset($firstRow[$column])) {
                $availableQuestions['teaching'][$key] = $column;
            }
        }
        
        // Check which English items exist
        $engItems = [
            'teaching' => 'Q06_C: Pelaksanaan PdP dalam BI->11. Pensyarah saya mengajar dalam bahasa Inggeris sepenuhnya.',
            'materials' => 'Q06_C: Pelaksanaan PdP dalam BI->12. Pensyarah saya menyediakan bahan pengajaran dalam bahasa Inggeris.',
            'discussion' => 'Q06_C: Pelaksanaan PdP dalam BI->13. Pensyarah mendorong saya membuat pembincangan dalam bahasa Inggeris.'
        ];
        
        foreach ($engItems as $key => $column) {
            if (isset($firstRow[$column])) {
                $availableQuestions['english'][$key] = $column;
            }
        }
        
        // Log what questions were found
        error_log("Found " . count($availableQuestions['professionalism']) . " professionalism questions");
        error_log("Found " . count($availableQuestions['teaching']) . " teaching questions");
        error_log("Found " . count($availableQuestions['english']) . " English questions");
    }
    
    // Now process the data
    foreach ($csvData as $row) {
        // Try different potential column names for teacher name
        $teacherName = null;
        if (isset($row['Q01_Nama Pensyarah']) && !empty($row['Q01_Nama Pensyarah'])) {
            $teacherName = trim($row['Q01_Nama Pensyarah']);
        } elseif (isset($row['Nama Pensyarah']) && !empty($row['Nama Pensyarah'])) {
            $teacherName = trim($row['Nama Pensyarah']);
        }
        
        // Get course code
        $courseCode = null;
        if (isset($row['Q03_Kod Subjek']) && !empty($row['Q03_Kod Subjek'])) {
            $courseCode = trim($row['Q03_Kod Subjek']);
        } elseif (isset($row['Kod Subjek']) && !empty($row['Kod Subjek'])) {
            $courseCode = trim($row['Kod Subjek']);
        }
        
        // Skip if essential data is missing
        if (empty($teacherName) || empty($courseCode)) {
            continue;
        }
        
        // Initialize teacher data if not exists
        if (!isset($teachers[$teacherName])) {
            // Initialize with empty scores structure based on available questions
            $professionalismItems = [];
            $teachingItems = [];
            $englishItems = [];
            
            // Initialize items based on what's available
            if (!empty($availableQuestions['professionalism'])) {
                foreach (array_keys($availableQuestions['professionalism']) as $key) {
                    $professionalismItems[$key] = 0;
                }
            }
            
            if (!empty($availableQuestions['teaching'])) {
                foreach (array_keys($availableQuestions['teaching']) as $key) {
                    $teachingItems[$key] = 0;
                }
            }
            
            if (!empty($availableQuestions['english'])) {
                foreach (array_keys($availableQuestions['english']) as $key) {
                    $englishItems[$key] = 0;
                }
            }
            
            $teachers[$teacherName] = [
                'name' => $teacherName,
                'gender' => isset($row['Q02_Jantina']) ? $row['Q02_Jantina'] : (isset($row['Jantina']) ? $row['Jantina'] : ''),
                'course' => $courseCode,
                'unit' => getUnitFromCourseCode($courseCode, $customMappings),
                'institution' => isset($row['Institution']) ? $row['Institution'] : 'Kolej Matrikulasi Pulau Pinang',
                'responses' => 0,
                'comments' => [],
                'scores' => [
                    'professionalism' => [
                        'total' => 0,
                        'items' => $professionalismItems
                    ],
                    'teaching' => [
                        'total' => 0,
                        'items' => $teachingItems
                    ],
                    'english' => [
                        'total' => 0,
                        'items' => $englishItems
                    ],
                    'has_english' => !empty($availableQuestions['english']) // Flag to indicate if English section exists
                ]
            ];
        }
        
        // Increment responses count
        $teachers[$teacherName]['responses']++;
        
        // Add comment if exists
        if (isset($row['Q07_Ulasan']) && !empty($row['Q07_Ulasan']) && $row['Q07_Ulasan'] != '-' && $row['Q07_Ulasan'] != 'Tiada') {
            $teachers[$teacherName]['comments'][] = $row['Q07_Ulasan'];
            
            // For performance, limit to storing at most 10 comments per teacher
            if (count($teachers[$teacherName]['comments']) > 10) {
                array_pop($teachers[$teacherName]['comments']);
            }
        } elseif (isset($row['Ulasan']) && !empty($row['Ulasan']) && $row['Ulasan'] != '-' && $row['Ulasan'] != 'Tiada') {
            $teachers[$teacherName]['comments'][] = $row['Ulasan'];
            
            // For performance, limit to storing at most 10 comments per teacher
            if (count($teachers[$teacherName]['comments']) > 10) {
                array_pop($teachers[$teacherName]['comments']);
            }
        }
        
        // Add professionalism scores
        if (!empty($availableQuestions['professionalism'])) {
            foreach ($availableQuestions['professionalism'] as $key => $column) {
                if (isset($row[$column]) && is_numeric($row[$column])) {
                    $teachers[$teacherName]['scores']['professionalism']['items'][$key] += (float)$row[$column];
                }
            }
        }
        
        // Add teaching scores
        if (!empty($availableQuestions['teaching'])) {
            foreach ($availableQuestions['teaching'] as $key => $column) {
                if (isset($row[$column]) && is_numeric($row[$column])) {
                    $teachers[$teacherName]['scores']['teaching']['items'][$key] += (float)$row[$column];
                }
            }
        }
        
        // Add English scores
        if (!empty($availableQuestions['english'])) {
            foreach ($availableQuestions['english'] as $key => $column) {
                if (isset($row[$column]) && is_numeric($row[$column])) {
                    $teachers[$teacherName]['scores']['english']['items'][$key] += (float)$row[$column];
                }
            }
        }
    }
    
    // Calculate averages
    foreach ($teachers as $name => $teacher) {
        $responseCount = $teacher['responses'];
        if ($responseCount > 0) {
            // Calculate professionalism average if items exist
            if (!empty($teacher['scores']['professionalism']['items'])) {
                foreach ($teachers[$name]['scores']['professionalism']['items'] as $key => $value) {
                    $teachers[$name]['scores']['professionalism']['items'][$key] = round($value / $responseCount, 1);
                }
                $teachers[$name]['scores']['professionalism']['total'] = round(
                    array_sum($teachers[$name]['scores']['professionalism']['items']) / count($teachers[$name]['scores']['professionalism']['items']),
                    1
                );
            } else {
                $teachers[$name]['scores']['professionalism']['total'] = 0;
            }
            
            // Calculate teaching average if items exist
            if (!empty($teacher['scores']['teaching']['items'])) {
                foreach ($teachers[$name]['scores']['teaching']['items'] as $key => $value) {
                    $teachers[$name]['scores']['teaching']['items'][$key] = round($value / $responseCount, 1);
                }
                $teachers[$name]['scores']['teaching']['total'] = round(
                    array_sum($teachers[$name]['scores']['teaching']['items']) / count($teachers[$name]['scores']['teaching']['items']),
                    1
                );
            } else {
                $teachers[$name]['scores']['teaching']['total'] = 0;
            }
            
            // Calculate English average if items exist
            if (!empty($teacher['scores']['english']['items'])) {
                foreach ($teachers[$name]['scores']['english']['items'] as $key => $value) {
                    $teachers[$name]['scores']['english']['items'][$key] = round($value / $responseCount, 1);
                }
                $teachers[$name]['scores']['english']['total'] = round(
                    array_sum($teachers[$name]['scores']['english']['items']) / count($teachers[$name]['scores']['english']['items']),
                    1
                );
            } else {
                $teachers[$name]['scores']['english']['total'] = 0;
            }
        }
    }
    
    return $teachers;
}

/**
 * Calculate overall average score correctly based on available sections
 * @param array $data Teacher data
 * @return float Overall average score
 */
function calculateOverallScore($data) {
    $sections = 0;
    $totalScore = 0;
    
    // Add professionalism score if it exists and is not zero
    if (isset($data['scores']['professionalism']['total']) && $data['scores']['professionalism']['total'] > 0) {
        $totalScore += $data['scores']['professionalism']['total'];
        $sections++;
    }
    
    // Add teaching score if it exists and is not zero
    if (isset($data['scores']['teaching']['total']) && $data['scores']['teaching']['total'] > 0) {
        $totalScore += $data['scores']['teaching']['total'];
        $sections++;
    }
    
    // Add English score ONLY if it exists AND is not zero
    // This is the key part - we only count sections with non-zero scores
    if (isset($data['scores']['english']['total']) && $data['scores']['english']['total'] > 0) {
        $totalScore += $data['scores']['english']['total'];
        $sections++;
    }
    
    // Calculate average of available sections
    if ($sections > 0) {
        return round($totalScore / $sections, 1);
    } else {
        return 0;
    }
}

/**
 * Get achievement level based on score
 * @param float $score The score to evaluate
 * @return string Achievement level
 */
function getAchievementLevel($score) {
    if ($score >= 9.0) return "CEMERLANG";
    if ($score >= 7.0) return "BAIK";
    if ($score >= 5.0) return "SEDERHANA";
    if ($score >= 3.0) return "LEMAH";
    return "SANGAT LEMAH";
}

/**
 * Generate PDF content for a teacher
 * @param array $teacherData Processed teacher data
 * @return string HTML content for PDF
 */
function generatePdfContent($teacherData) {
    // Calculate overall average score
    $overallScore = calculateOverallScore($teacherData);
    
    $achievementLevel = getAchievementLevel($overallScore);
    
    // Get college average (placeholder - in a real app this would come from database)
    $collegeAverage = 9.9;
    
    $html = '
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.5;
        }
        h1 {
            font-size: 16pt;
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .info-table td {
            padding: 5px;
        }
        .score-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .score-table th, .score-table td {
            border: 1px solid #000;
            padding: 8px;
        }
        .score-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .comment-section {
            margin-top: 20px;
            border: 1px solid #000;
            padding: 10px;
        }
        .category-header {
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #ccc;
            padding-top: 10px;
            font-size: 10pt;
        }
    </style>
    
    <h1>LAPORAN INDIVIDU PENILAIAN ePP PENSYARAH KMPP<br>SEMESTER 2 SESI 2023/2024</h1>
    
    <p>TUAN/PUAN,</p>
    <p>Berikut adalah keputusan e-Penilaian Pensyarah (ePP) oleh pelajar yang telah dilaksanakan pada Semester 2 sesi 2023/2024 di Kolej Matrikulasi Pulau Pinang.</p>
    
    <table class="info-table">
        <tr>
            <td width="30%">Nama Pensyarah</td>
            <td width="70%">: ' . htmlspecialchars($teacherData['name']) . '</td>
        </tr>
        <tr>
            <td>Kursus</td>
            <td>: ' . htmlspecialchars($teacherData['course']) . '</td>
        </tr>
        <tr>
            <td>Skor Purata Penilaian Pertama Pensyarah</td>
            <td>: ' . $overallScore . '</td>
        </tr>
        <tr>
            <td>Skor Purata Kolej</td>
            <td>: ' . $collegeAverage . '</td>
        </tr>
        <tr>
            <td>Bilangan Respon Pelajar</td>
            <td>: ' . $teacherData['responses'] . '</td>
        </tr>
        <tr>
            <td>Peratus Pelajar Menilai</td>
            <td>: 100%</td>
        </tr>
    </table>
    
    <table class="score-table">
        <tr>
            <th width="10%">BIL</th>
            <th width="70%">ITEM</th>
            <th width="20%">SKOR</th>
        </tr>';
    
    // Professionalism section
    if (!empty($teacherData['scores']['professionalism']['items'])) {
        $html .= '
        <tr>
            <td>A</td>
            <td class="category-header">Profesionalisme</td>
            <td>' . $teacherData['scores']['professionalism']['total'] . '</td>
        </tr>';
        
        foreach ($teacherData['scores']['professionalism']['items'] as $key => $score) {
            $label = '';
            switch ($key) {
                case 'appearance':
                    $label = 'Penampilan pensyarah menarik minat saya untuk belajar.';
                    break;
                case 'punctuality':
                    $label = 'Pensyarah menepati waktu yang ditetapkan.';
                    break;
                case 'care':
                    $label = 'Pensyarah mengambil berat terhadap pembelajaran saya.';
                    break;
                default:
                    $label = $key;
            }
            
            $html .= '
            <tr>
                <td></td>
                <td>' . $label . '</td>
                <td>' . $score . '</td>
            </tr>';
        }
    }
    
    // Teaching section
    if (!empty($teacherData['scores']['teaching']['items'])) {
        $html .= '
        <tr>
            <td>B</td>
            <td class="category-header">Pengajaran Dan Pembelajaran</td>
            <td>' . $teacherData['scores']['teaching']['total'] . '</td>
        </tr>';
        
        foreach ($teacherData['scores']['teaching']['items'] as $key => $score) {
            $label = '';
            switch ($key) {
                case 'knowledge':
                    $label = 'Pensyarah mempunyai pengetahuan yang luas tentang kursus yang diajar.';
                    break;
                case 'understanding':
                    $label = 'Saya faham isi kandungan kursus yang disampaikan oleh pensyarah.';
                    break;
                case 'materials':
                    $label = 'Bahan bantu mengajar pensyarah seperti nota / bahan aktiviti meningkatkan pemahaman saya.';
                    break;
                case 'methods':
                    $label = 'Kaedah pengajaran pensyarah saya membantu saya memahami isi kandungan kursus.';
                    break;
                case 'exercises':
                    $label = 'Latihan/tugasan/kuiz yang dilaksanakan di dalam kelas oleh pensyarah menguji pemahaman saya.';
                    break;
                case 'online_learning':
                    $label = 'Pembelajaran dalam talian (online) yang dilaksanakan oleh pensyarah membantu proses pembelajaran saya.';
                    break;
                case 'online_materials':
                    $label = 'Bahan PdP dalam talian dapat diakses dengan mudah.';
                    break;
                default:
                    $label = $key;
            }
            
            $html .= '
            <tr>
                <td></td>
                <td>' . $label . '</td>
                <td>' . $score . '</td>
            </tr>';
        }
    }
    
    // English section
    if (!empty($teacherData['scores']['english']['items'])) {
        $html .= '
        <tr>
            <td>C</td>
            <td class="category-header">Pelaksanaan PdP dalam Bahasa Inggeris</td>
            <td>' . $teacherData['scores']['english']['total'] . '</td>
        </tr>';
        
        foreach ($teacherData['scores']['english']['items'] as $key => $score) {
            $label = '';
            switch ($key) {
                case 'teaching':
                    $label = 'Pensyarah saya mengajar dalam bahasa Inggeris sepenuhnya.';
                    break;
                case 'materials':
                    $label = 'Pensyarah saya menyediakan bahan pengajaran dalam bahasa Inggeris.';
                    break;
                case 'discussion':
                    $label = 'Pensyarah mendorong saya membuat perbincangan dalam Bahasa Inggeris.';
                    break;
                default:
                    $label = $key;
            }
            
            $html .= '
            <tr>
                <td></td>
                <td>' . $label . '</td>
                <td>' . $score . '</td>
            </tr>';
        }
    }
    
    $html .= '
        <tr>
            <td colspan="2" class="category-header">Skor Purata</td>
            <td>' . $overallScore . '</td>
        </tr>
        <tr>
            <td colspan="2" class="category-header">Tahap Pencapaian Skor</td>
            <td>' . $achievementLevel . '</td>
        </tr>
    </table>
    
    <div>
        <h3>Ulasan Keseluruhan/Cadangan Penambahbaikan</h3>
        <div class="comment-section">';
        
    if (count($teacherData['comments']) > 0) {
        foreach ($teacherData['comments'] as $index => $comment) {
            $html .= '<p>Pelajar ' . ($index + 1) . ': ' . htmlspecialchars($comment) . '</p>';
        }
    } else {
        $html .= '<p>Tiada ulasan.</p>';
    }
        
    $html .= '
        </div>
    </div>
    
    <div>
        <table class="info-table">
            <tr>
                <th>Julat Skor</th>
                <th>Tahap Pencapaian Skor</th>
            </tr>
            <tr>
                <td>1.00 – 2.99</td>
                <td>Sangat Lemah</td>
            </tr>
            <tr>
                <td>3.00 – 4.99</td>
                <td>Lemah</td>
            </tr>
            <tr>
                <td>5.00 – 6.99</td>
                <td>Sederhana</td>
            </tr>
            <tr>
                <td>7.00 – 8.99</td>
                <td>Baik</td>
            </tr>
            <tr>
                <td>9.00 – 10.00</td>
                <td>Cemerlang</td>
            </tr>
        </table>
    </div>
    
    <div class="footer">
        <p><strong>Catatan:</strong></p>
        <ol>
            <li>Penilaian pertama oleh pelajar dilaksanakan pada minggu PdP ke 6 hingga 8. Manakala penilaian kedua oleh pelajar akan dilaksanakan pada minggu PdP ke 12 hingga 14.</li>
            <li>Penilaian kedua HANYA akan dilaksanakan ke atas pensyarah yang memperoleh skor purata kurang daripada 8.00 pada penilaian pertama.</li>
            <li>Tandaan (-) menunjukkan item penilaian berkenaan tidak dinilai bagi kursus ini.</li>
        </ol>
        
        <p>Sekian, terima kasih.</p>
        <p>"MALAYSIA MADANI"</p>
        <p>"BERKHIDMAT UNTUK NEGARA"</p>
        <p>Saya yang menjalankan amanah,<br>
        Jawatankuasa ePP dan Pemantauan PdP Pensyarah<br>
        Kolej Matrikulasi Pulau Pinang</p>
        <p><em>Maklumat ini dijana oleh komputer, tiada tandatangan diperlukan.</em></p>
    </div>';
    
    return $html;
}



?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPP Analitik - Penilaian Pensyarah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #6366f1;
            --accent-color: #a5b4fc;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-text: #1f2937;
            --light-bg: #f8fafc;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f9fafb;
            color: var(--dark-text);
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            padding: 1rem 0;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: white !important;
        }
        
        .brand-icon {
            background: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            margin-right: 0.5rem;
            font-size: 1.5rem;
            color: var(--primary-color);
        }
        
        .logo-text {
            display: inline-block;
            vertical-align: middle;
        }
        
        .main-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .page-header h1 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .page-header p {
            color: #6b7280;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header {
            background: var(--light-bg);
            border-bottom: none;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            font-size: 1.1rem;
        }
        
        .card-header-icon {
            background: var(--accent-color);
            color: var(--primary-color);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 0.5rem;
            padding: 0.625rem 1rem;
            border: 1px solid #d1d5db;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(165, 180, 252, 0.25);
        }
        
        .btn {
            border-radius: 0.5rem;
            padding: 0.625rem 1.25rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .btn-success:hover, .btn-success:focus {
            background-color: #0ca678;
            border-color: #0ca678;
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
            color: white;
        }
        
        .btn-warning:hover, .btn-warning:focus {
            background-color: #d97706;
            border-color: #d97706;
            color: white;
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-icon {
            display: inline-flex;
            align-items: center;
        }
        
        .btn-icon i {
            margin-right: 0.5rem;
        }
        
        .unit-mapping-row {
            position: relative;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-radius: 0.5rem;
            background-color: var(--light-bg);
        }
        
        .remove-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
        }
        
        .preview-container {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 2rem;
            margin-top: 1.5rem;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .action-card {
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.2s ease;
        }
        
        .action-card:hover {
            border-color: var(--accent-color);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }
        
        .action-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        
        .action-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--light-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 1.25rem;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .action-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--dark-text);
            margin: 0;
        }
        
        .action-desc {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        
        .footer {
            text-align: center;
            padding: 2rem 0;
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .alert {
            border-radius: 0.5rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border: none;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        /* Custom styles for sticky nav tabs */
        .sticky-tabs {
            position: sticky;
            top: 0;
            z-index: 100;
            background-color: white;
            padding: 1rem 0;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .nav-tabs {
            border-bottom: none;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-radius: 0.5rem;
            padding: 0.75rem 1.25rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
            background-color: var(--light-bg);
        }
        
        .nav-tabs .nav-link.active {
            color: white;
            background-color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <span class="brand-icon"><i class="bi bi-bar-chart-fill"></i></span>
                <span class="logo-text">EPP Analitik</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#"><i class="bi bi-house-door"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="debug_csv.php"><i class="bi bi-bug"></i> Debug CSV</a>
                    </li>
                    <?php if (file_exists('college_report.html')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="college_report.html" target="_blank"><i class="bi bi-file-earmark-text"></i> College Report</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <div class="page-header">
            <h1>EPP Analitik</h1>
            <p>Sistem pemprosesan data penilaian pensyarah automatik untuk Kolej Matrikulasi Pulau Pinang</p>
        </div>
        
        <?php echo $message; ?>
        
        <div class="card">
            <div class="card-header">
                <span class="card-header-icon"><i class="bi bi-cloud-upload"></i></span>
                Muat Naik Fail CSV
            </div>
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" onsubmit="console.log('Form is submitting!');">
                    <div class="mb-4">
                        <label for="csvFile" class="form-label">Pilih fail CSV</label>
                        <input class="form-control" type="file" id="csvFile" name="csvFile" required>
                        <div class="form-text text-muted">Fail CSV hendaklah mengandungi data penilaian pensyarah dari sistem e-Penilaian Pensyarah (ePP).</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="semester" class="form-label">Semester</label>
                            <select class="form-select" id="semester" name="semester" required>
                                <option value="1">Semester 1</option>
                                <option value="2" selected>Semester 2</option>
                                <option value="3">Semester 3</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="session" class="form-label">Sesi</label>
                            <input type="text" class="form-control" id="session" name="session" value="2023/2024" placeholder="cth. 2023/2024" required>
                        </div>
                        
                       
                        
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <span class="card-header-icon"><i class="bi bi-diagram-3"></i></span>
                            Pemetaan Nama Unit (Pilihan)
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Tentukan nama unit tersuai untuk awalan kod kursus (cth., WE = Bahasa Inggeris)</p>
                            
                            <div id="unitMappingsContainer">
                                <div class="unit-mapping-row">
                                    <div class="row">
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label">Awalan Kod Kursus</label>
                                            <input type="text" class="form-control" name="unitPrefix[]" placeholder="cth. WE">
                                        </div>
                                        <div class="col-md-8 mb-2">
                                            <label class="form-label">Nama Unit</label>
                                            <input type="text" class="form-control" name="unitName[]" placeholder="cth. Unit Bahasa Inggeris">
                                        </div>
                                    </div>
                                </div>
                                <div id="unitMappingsRows"></div>
                                
                                <div class="text-center mt-3">
                                    <button type="button" class="btn btn-sm btn-outline-primary add-unit-mapping">
                                        <i class="bi bi-plus-circle"></i> Tambah Unit Baharu
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-icon">
                            <i class="bi bi-upload"></i> Muat Naik & Proses
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if (!empty($processedData)): ?>
        <div class="card">
            <div class="card-header">
                <span class="card-header-icon"><i class="bi bi-file-earmark-text"></i></span>
                Jana Laporan
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Berjaya menjumpai <?php echo count($processedData); ?> pensyarah dalam data CSV. Sila pilih jenis laporan yang ingin dijana:
                </div>
                
                <div class="sticky-tabs mb-4">
                    <ul class="nav nav-tabs" id="reportTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard-tab-pane" type="button" role="tab">
                                <i class="bi bi-speedometer2"></i> Kolej
                            </button>
                        </li>
                       
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="unit-tab" data-bs-toggle="tab" data-bs-target="#unit-tab-pane" type="button" role="tab">
                                <i class="bi bi-diagram-3"></i> Unit
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="individual-tab" data-bs-toggle="tab" data-bs-target="#individual-tab-pane" type="button" role="tab">
                                <i class="bi bi-person"></i> Individu
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary-tab-pane" type="button" role="tab">
                                <i class="bi bi-table"></i> Ringkasan
                            </button>
                        </li>
                    </ul>
                </div>
                
                <div class="tab-content" id="reportTabsContent">
                    <!-- Dashboard Tab -->
                    <div class="tab-pane fade show active" id="dashboard-tab-pane" role="tabpanel" aria-labelledby="dashboard-tab" tabindex="0">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="action-card">
                                    <div class="action-card-header">
                                        <div class="action-icon">
                                            <i class="bi bi-building"></i>
                                        </div>
                                        <h3 class="action-title">Dashboard Kolej</h3>
                                    </div>
                                    <p class="action-desc">Memaparkan statistik prestasi merentas semua unit dan jabatan di kolej.</p>
                                    <form action="college_dashboard.php" method="post">
                                        <input type="hidden" name="data" value="<?php echo htmlspecialchars(json_encode($processedData)); ?>">
                                        <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester); ?>">
                                        <input type="hidden" name="session" value="<?php echo htmlspecialchars($session); ?>">
                                        <input type="hidden" name="collegeAverage" value="<?php echo htmlspecialchars($collegeAverage); ?>">
                                        <input type="hidden" name="unitMappings" value="<?php echo htmlspecialchars(json_encode($unitMappings)); ?>">
                                        <button type="submit" class="btn btn-primary w-100 btn-icon">
                                            <i class="bi bi-bar-chart"></i> Lihat Dashboard Kolej
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="action-card">
                                    <div class="action-card-header">
                                        <div class="action-icon">
                                            <i class="bi bi-file-earmark-text"></i>
                                        </div>
                                        <h3 class="action-title">Laporan Kolej (HTML)</h3>
                                    </div>
                                    <p class="action-desc">Menjana laporan HTML statik yang menunjukkan statistik prestasi semua unit di kolej.</p>
                                    <form action="generate_college_report.php" method="post">
                                        <input type="hidden" name="data" value="<?php echo htmlspecialchars(json_encode($processedData)); ?>">
                                        <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester); ?>">
                                        <input type="hidden" name="session" value="<?php echo htmlspecialchars($session); ?>">
                                        <input type="hidden" name="collegeAverage" value="<?php echo htmlspecialchars($collegeAverage); ?>">
                                        <input type="hidden" name="unitMappings" value="<?php echo htmlspecialchars(json_encode($unitMappings)); ?>">
                                        <button type="submit" class="btn btn-success w-100 btn-icon">
                                            <i class="bi bi-file-earmark-code"></i> Jana Laporan Kolej HTML
                                        </button>
                                    </form>
                                    
                                    <?php if (file_exists('college_report.html')): ?>
                                    <div class="mt-2">
                                        <a href="college_report.html" class="btn btn-outline-primary w-100 btn-icon" target="_blank">
                                            <i class="bi bi-eye"></i> Lihat Laporan Kolej
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Individual Tab -->
                    <div class="tab-pane fade" id="individual-tab-pane" role="tabpanel" aria-labelledby="individual-tab" tabindex="0">
                        <div class="action-card">
                            <div class="action-card-header">
                                <div class="action-icon">
                                    <i class="bi bi-person-badge"></i>
                                </div>
                                <h3 class="action-title">Laporan Individu Pensyarah</h3>
                            </div>
                            <p class="action-desc">Jana PDF atau lihat dashboard untuk pensyarah individu.</p>
                            
                            <form action="generate_pdf.php" method="post" target="_blank">
                                <div class="mb-3">
                                    <label for="teacherSelect" class="form-label">Pilih pensyarah:</label>
                                    <select class="form-select" id="teacherSelect" name="teacher" required>
                                        <option value="">-- Pilih Pensyarah --</option>
                                        <?php foreach ($processedData as $name => $data): ?>
                                        <option value="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?> (<?php echo htmlspecialchars($data['course']); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="d-flex">
                                    <button type="submit" class="btn btn-primary me-2 flex-grow-1 btn-icon">
                                        <i class="bi bi-file-pdf"></i> Jana Laporan PDF
                                    </button>
                                    <button type="submit" class="btn btn-success flex-grow-1 btn-icon" formaction="teacher_dashboard.php">
                                        <i class="bi bi-speedometer2"></i> Lihat Dashboard
                                    </button>
                                </div>
                                
                                <input type="hidden" name="data" value="<?php echo htmlspecialchars(json_encode($processedData)); ?>">
                                <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester); ?>">
                                <input type="hidden" name="session" value="<?php echo htmlspecialchars($session); ?>">
                                <input type="hidden" name="collegeAverage" value="<?php echo htmlspecialchars($collegeAverage); ?>">
                            </form>
                        </div>
                        
                        <div class="action-card">
                            <div class="action-card-header">
                                <div class="action-icon">
                                    <i class="bi bi-file-pdf"></i>
                                </div>
                                <h3 class="action-title">Jana PDF untuk Semua Pensyarah</h3>
                            </div>
                            <p class="action-desc">Cipta fail PDF untuk setiap pensyarah dan simpan semua fail dalam satu folder.</p>
                            
                            <form action="generate_all_pdfs.php" method="post">
                                <input type="hidden" name="data" value="<?php echo htmlspecialchars(json_encode($processedData)); ?>">
                                <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester); ?>">
                                <input type="hidden" name="session" value="<?php echo htmlspecialchars($session); ?>">
                                <input type="hidden" name="collegeAverage" value="<?php echo htmlspecialchars($collegeAverage); ?>">
                                <input type="hidden" name="unitMappings" value="<?php echo htmlspecialchars(json_encode($unitMappings)); ?>">
                                <button type="submit" class="btn btn-warning w-100 btn-icon">
                                    <i class="bi bi-file-pdf"></i> Jana Semua Laporan PDF
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Unit Tab -->
                    <div class="tab-pane fade" id="unit-tab-pane" role="tabpanel" aria-labelledby="unit-tab" tabindex="0">
                        <div class="action-card">
                            <div class="action-card-header">
                                <div class="action-icon">
                                    <i class="bi bi-diagram-3"></i>
                                </div>
                                <h3 class="action-title">Laporan Berdasarkan Unit</h3>
                            </div>
                            <p class="action-desc">Cipta laporan HTML berasingan yang disusun mengikut unit/jabatan, dengan setiap unit menunjukkan semua pensyarahnya.</p>
                            
                            <form action="generate_unit_reports.php" method="post">
                                <input type="hidden" name="data" value="<?php echo htmlspecialchars(json_encode($processedData)); ?>">
                                <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester); ?>">
                                <input type="hidden" name="session" value="<?php echo htmlspecialchars($session); ?>">
                                <input type="hidden" name="collegeAverage" value="<?php echo htmlspecialchars($collegeAverage); ?>">
                                <input type="hidden" name="unitMappings" value="<?php echo htmlspecialchars(json_encode($unitMappings)); ?>">
                                <button type="submit" class="btn btn-primary w-100 btn-icon">
                                    <i class="bi bi-folder2-open"></i> Jana Laporan Unit
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Summary Tab -->
                    <div class="tab-pane fade" id="summary-tab-pane" role="tabpanel" aria-labelledby="summary-tab" tabindex="0">
                        <div class="action-card">
                            <div class="action-card-header">
                                <div class="action-icon">
                                    <i class="bi bi-table"></i>
                                </div>
                                <h3 class="action-title">Laporan Ringkasan HTML</h3>
                            </div>
                            <p class="action-desc">Cipta laporan HTML gabungan yang menunjukkan skor penilaian semua pensyarah dalam format jadual.</p>
                            
                            <form action="generate_html_summary.php" method="post">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="1" id="saveToFile" name="saveToFile">
                                        <label class="form-check-label" for="saveToFile">
                                            Simpan laporan sebagai fail HTML yang boleh dimuat turun
                                        </label>
                                    </div>
                                </div>
                                
                                <input type="hidden" name="data" value="<?php echo htmlspecialchars(json_encode($processedData)); ?>">
                                <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester); ?>">
                                <input type="hidden" name="session" value="<?php echo htmlspecialchars($session); ?>">
                                <input type="hidden" name="collegeAverage" value="<?php echo htmlspecialchars($collegeAverage); ?>">
                                <input type="hidden" name="unitMappings" value="<?php echo htmlspecialchars(json_encode($unitMappings)); ?>">
                                <button type="submit" class="btn btn-primary w-100 btn-icon">
                                    <i class="bi bi-table"></i> Jana Laporan Ringkasan HTML
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <span class="card-header-icon"><i class="bi bi-eye"></i></span>
                Pratonton Laporan
            </div>
            <div class="card-body">
                <p>Pilih pensyarah untuk pratonton laporan:</p>
                
                <select class="form-select mb-3" id="previewSelect">
                    <option value="">-- Pilih Pensyarah --</option>
                    <?php foreach ($processedData as $name => $data): ?>
                    <option value="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?> (<?php echo htmlspecialchars($data['course']); ?>)</option>
                    <?php endforeach; ?>
                </select>
                
                <div class="preview-container shadow-sm" id="previewContent" style="display: none;"></div>
                
                <input type="hidden" id="semester" value="<?php echo htmlspecialchars($semester); ?>">
                <input type="hidden" id="session" value="<?php echo htmlspecialchars($session); ?>">
                <input type="hidden" id="collegeAverage" value="<?php echo htmlspecialchars($collegeAverage); ?>">
            </div>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>EPP Analitik © <?php echo date('Y'); ?> | Developed by OneExa EduTech Solutions for Kolej Matrikulasi Pulau Pinang</p>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Debug form submission
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                console.log('Form submitted');
                // Log form data for debugging
                const formData = new FormData(form);
                for (let pair of formData.entries()) {
                    console.log(pair[0] + ': ' + pair[1]);
                }
            });
        });
        
        // Add button functionality for unit mappings
        document.addEventListener('DOMContentLoaded', function() {
            const addUnitBtn = document.querySelector('.add-unit-mapping');
            const unitMappingsRows = document.getElementById('unitMappingsRows');
            
            if (addUnitBtn) {
                addUnitBtn.addEventListener('click', function() {
                    const newRow = document.createElement('div');
                    newRow.className = 'unit-mapping-row position-relative';
                    newRow.innerHTML = `
                        <button type="button" class="btn btn-sm btn-danger remove-btn">
                            <i class="bi bi-x"></i>
                        </button>
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Awalan Kod Kursus</label>
                                <input type="text" class="form-control" name="unitPrefix[]" placeholder="cth. WE">
                            </div>
                            <div class="col-md-8 mb-2">
                                <label class="form-label">Nama Unit</label>
                                <input type="text" class="form-control" name="unitName[]" placeholder="cth. Unit Bahasa Inggeris">
                            </div>
                        </div>
                    `;
                    unitMappingsRows.appendChild(newRow);
                    
                    // Add event listener to the remove button
                    newRow.querySelector('.remove-btn').addEventListener('click', function() {
                        unitMappingsRows.removeChild(newRow);
                    });
                });
            }
        });
        
        // Store processed data in JavaScript for preview
        <?php if (!empty($processedData)): ?>
        const teacherData = <?php echo json_encode($processedData); ?>;
        
        document.getElementById('previewSelect').addEventListener('change', function() {
            const selectedTeacher = this.value;
            const previewContainer = document.getElementById('previewContent');
            
            if (selectedTeacher && teacherData[selectedTeacher]) {
                // Show preview container
                previewContainer.style.display = 'block';
                
                // Get session, semester, and college average values
                const semester = document.getElementById('semester').value;
                const session = document.getElementById('session').value;
                const collegeAverage = document.getElementById('collegeAverage').value;
                
                // Show loading indicator
                previewContainer.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Menjana pratonton...</p></div>';
                
                // Make AJAX request to get HTML preview
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'preview_report.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (this.status === 200) {
                        previewContainer.innerHTML = this.responseText;
                    } else {
                        previewContainer.innerHTML = '<div class="alert alert-danger">Error generating preview.</div>';
                    }
                };
                xhr.send('teacher=' + encodeURIComponent(selectedTeacher) + 
                        '&data=' + encodeURIComponent(JSON.stringify(teacherData)) + 
                        '&semester=' + encodeURIComponent(semester) + 
                        '&session=' + encodeURIComponent(session) + 
                        '&collegeAverage=' + encodeURIComponent(collegeAverage));
            } else {
                // Hide preview container if no teacher selected
                previewContainer.style.display = 'none';
                previewContainer.innerHTML = '';
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
                                
