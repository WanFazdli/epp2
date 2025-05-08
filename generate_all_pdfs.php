<?php
// Define the TCPDF path properly
define('TCPDF_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'tcpdf' . DIRECTORY_SEPARATOR);
require_once(TCPDF_PATH . 'tcpdf.php');

// Fix for PHP 8.1+ deprecated warnings in TCPDF
if (PHP_VERSION_ID >= 80100) {
    $original_reporting = error_reporting(E_ALL & ~E_DEPRECATED);
}

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED); // Suppress deprecated warnings

/**
 * Check if teacher data has valid English section data
 * @param array $teacherData The teacher data to evaluate
 * @return bool True if valid English data exists, false otherwise
 */
function hasValidEnglishData($teacherData) {
    return isset($teacherData['scores']['english']) && 
           isset($teacherData['scores']['english']['total']) && 
           is_numeric($teacherData['scores']['english']['total']) &&
           $teacherData['scores']['english']['total'] > 0 &&
           isset($teacherData['scores']['english']['items']) &&
           !empty($teacherData['scores']['english']['items']);
}

/**
 * Calculate overall score based on available sections
 * @param array $teacherData The teacher data to evaluate
 * @return float The calculated overall score
 */
function calculateOverallScore($teacherData) {
    $avgA = $teacherData['scores']['professionalism']['total'];
    $avgB = $teacherData['scores']['teaching']['total'];
    
    if (hasValidEnglishData($teacherData)) {
        $avgC = $teacherData['scores']['english']['total'];
        return round(($avgA + $avgB + $avgC) / 3, 1);
    } else {
        return round(($avgA + $avgB) / 2, 1);
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

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['data'])) {
    $teacherData = json_decode($_POST['data'], true);
    
    if (!$teacherData || empty($teacherData)) {
        die("No teacher data found. Please go back and try again.");
    }
    
    // Get session, semester, and college average
    $semester = isset($_POST['semester']) ? $_POST['semester'] : '2';
    $session = isset($_POST['session']) ? $_POST['session'] : '2023/2024';
    $collegeAverage = isset($_POST['collegeAverage']) ? $_POST['collegeAverage'] : '9.9';
    
    // Create main output directory with absolute path
    $mainOutputDir = __DIR__ . DIRECTORY_SEPARATOR . 'teacher_reports_' . date('Y-m-d_H-i-s');
    if (!file_exists($mainOutputDir)) {
        if (!mkdir($mainOutputDir, 0777, true)) {
            die("Failed to create main directory for PDF files.");
        }
    }
    
    // Track successes and failures
    $successCount = 0;
    $failCount = 0;
    $errors = [];
    $unitFolders = []; // Keep track of created unit folders
    
    // Start output buffering to capture any errors or warnings
    ob_start();
    
    // Generate PDF for each teacher
    foreach ($teacherData as $teacherName => $data) {
        try {
            // Get the unit/course from the teacher data
            $unit = $data['course'];
            $safeUnit = preg_replace('/[^a-zA-Z0-9_-]/', '_', $unit);
            
            // Create unit-specific subfolder if it doesn't exist
            $unitDir = $mainOutputDir . DIRECTORY_SEPARATOR . $safeUnit;
            if (!file_exists($unitDir)) {
                if (!mkdir($unitDir, 0777, true)) {
                    throw new Exception("Failed to create unit directory: $safeUnit");
                }
                $unitFolders[] = $safeUnit;
            }
            
            // Generate safe filename
            $safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $teacherName);
            $outputFile = $unitDir . DIRECTORY_SEPARATOR . $safeFilename . '.pdf';
            
            // Generate PDF content
            $html = generatePdfContent($data, $semester, $session, $collegeAverage);
            
            // Create new PDF document
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('Teacher Evaluation System');
            $pdf->SetAuthor('Kolej Matrikulasi Pulau Pinang');
            $pdf->SetTitle('Teacher Evaluation Report - ' . $data['name']);
            $pdf->SetSubject('Teacher Evaluation');
            
            // Remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
            // Set margins
            $pdf->SetMargins(15, 15, 15);
            
            // Set auto page breaks
            $pdf->SetAutoPageBreak(true, 15);
            
            // Add a page
            $pdf->AddPage();
            
            // Write HTML content
            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Save PDF to file
            $pdf->Output($outputFile, 'F');
            
            $successCount++;
        } catch (Exception $e) {
            $failCount++;
            $errors[] = "Error generating PDF for $teacherName: " . $e->getMessage();
        }
    }
    
    // Get any output or errors
    $output = ob_get_clean();
    
    // Restore error reporting if changed
    if (PHP_VERSION_ID >= 80100 && isset($original_reporting)) {
        error_reporting($original_reporting);
    }
    
    // Create ZIP archive of the generated PDFs (including folder structure)
    $zipFileName = 'teacher_reports_by_unit_' . date('Y-m-d_H-i-s') . '.zip';
    $zipFile = __DIR__ . DIRECTORY_SEPARATOR . $zipFileName;
    
    // Check if ZIP extension is loaded
    if (!extension_loaded('zip')) {
        $errors[] = "ZIP extension is not enabled. Please enable it in your php.ini file.";
        $zipCreated = false;
    } else {
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($mainOutputDir),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($mainOutputDir) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
            
            $zip->close();
            $zipCreated = true;
        } else {
            $errors[] = "Failed to create ZIP archive.";
            $zipCreated = false;
        }
    }
    
    // Show success message with results
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>PDF Generation Results</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <h1>PDF Generation Results</h1>
            
            <div class="alert alert-info">
                <h4>Summary:</h4>
                <p>Successfully generated ' . $successCount . ' PDF reports organized by unit.</p>
                <p>Failed to generate ' . $failCount . ' PDF reports.</p>
                <p>Created ' . count($unitFolders) . ' unit folders.</p>
            </div>';
            
    if ($successCount > 0) {
        $relativeMainDir = basename($mainOutputDir);
        
        echo '<div class="card mb-4">
                <div class="card-header">
                    Download Options
                </div>
                <div class="card-body">';
        
        if (isset($zipCreated) && $zipCreated) {
            $relativeZip = basename($zipFile);
            echo '<p>You can download all PDFs (organized by unit) as a ZIP file or access the folder directly:</p>
                  <a href="' . $relativeZip . '" class="btn btn-primary me-2">Download ZIP Archive</a>';
        } else {
            echo '<p>ZIP archive not available. You can access the PDFs through the folder directly:</p>';
        }
        
        echo '<a href="' . $relativeMainDir . '/" class="btn btn-secondary">View PDF Folder</a>
                </div>
            </div>';
            
        // List created unit folders
        echo '<div class="card mb-4">
                <div class="card-header">
                    Unit Folders Created
                </div>
                <div class="card-body">
                    <ul>';
        foreach ($unitFolders as $unitFolder) {
            echo '<li><a href="' . $relativeMainDir . '/' . $unitFolder . '/" target="_blank">' . $unitFolder . '</a></li>';
        }
        echo '</ul>
                </div>
            </div>';
    }
    
    if (!empty($errors)) {
        echo '<div class="card mb-4">
                <div class="card-header text-white bg-danger">
                    Errors
                </div>
                <div class="card-body">
                    <ul>';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul>
                </div>
            </div>';
    }
    
    if (!empty($output)) {
        echo '<div class="card mb-4">
                <div class="card-header">
                    System Output
                </div>
                <div class="card-body">
                    <pre>' . htmlspecialchars($output) . '</pre>
                </div>
            </div>';
    }
    
    echo '<a href="index.php" class="btn btn-outline-secondary">Back to Home</a>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>';
    
    exit;
} else {
    // If accessed directly without POST data
    header("Location: index.php");
    exit;
}

/**
 * Generate PDF content for a teacher
 * @param array $teacherData Processed teacher data
 * @param string $semester Semester number
 * @param string $session Academic session
 * @param string $collegeAverage College average score
 * @return string HTML content for PDF
 */
function generatePdfContent($teacherData, $semester = '2', $session = '2023/2024', $collegeAverage = '9.9') {
    // Check if English section is applicable for this teacher
    $hasEnglish = hasValidEnglishData($teacherData);
    
    // Calculate overall average score based on available sections
    $overallScore = calculateOverallScore($teacherData);
    $achievementLevel = getAchievementLevel($overallScore);
    
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
		 .info-table2 {
            width: 60%;
            margin-bottom: 20px;
            border: 0.5px solid #000;
        }
        .info-table2 td {
            border: 0.5px solid black;
            border-collapse: collapse;
            padding: 5px;

        .info-table td {
            padding: 5px;
        }
        .score-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .score-table th {
            border: 1px solid #000;
            background-color: #f2f2f2;
            font-weight: bold;
            padding: 8px;
        }
        .score-table td {
            padding: 8px;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
        }
        /* Section header rows */
        .score-table tr.section-header td {
            border-top: 1px solid #000;
            font-weight: bold;
        }
        /* Section footer rows (averages) */
        .score-table tr.section-footer td {
            border-bottom: 1px solid #000;
            font-weight: bold;
        }
        /* Final rows */
        .score-table tr.final-row td {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            font-weight: bold;
        }
        .category-header {
            font-weight: bold;
        }
        .comment-section {
            margin-top: 20px;
            border: 1px solid #000;
            padding: 10px;
        }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #ccc;
            padding-top: 10px;
            font-size: 10pt;
        }
        .not-applicable {
            color: #777;
            font-style: italic;
        }
    </style>
    
    <h1>LAPORAN INDIVIDU PENILAIAN ePP PENSYARAH KMPP<br>SEMESTER ' . $semester . ' SESI ' . $session . '</h1>
    
    <p>TUAN/PUAN,</p>
    <p>Berikut adalah keputusan e-Penilaian Pensyarah (ePP) oleh pelajar yang telah dilaksanakan pada Semester ' . $semester . ' sesi ' . $session . ' di Kolej Matrikulasi Pulau Pinang.</p>
    
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
            <td>:     </td>
        </tr>
    </table>
    
    <table class="score-table">
        <tr>
            <th width="10%">BIL</th>
            <th width="70%">ITEM</th>
            <th width="20%">SKOR</th>
        </tr>
        <tr class="section-header">
            <td>A</td>
            <td class="category-header">Profesionalisme</td>
             <td></td>
        </tr>
        <tr>
            <td></td>
            <td>Penampilan pensyarah menarik minat saya untuk belajar.</td>
            <td>' . $teacherData['scores']['professionalism']['items']['appearance'] . '</td>
        </tr>
        <tr>
            <td></td>
            <td>Pensyarah menepati waktu yang ditetapkan.</td>
            <td>' . $teacherData['scores']['professionalism']['items']['punctuality'] . '</td>
        </tr>
        <tr>
            <td></td>
            <td>Pensyarah mengambil berat terhadap pembelajaran saya.</td>
            <td>' . $teacherData['scores']['professionalism']['items']['care'] . '</td>
        </tr>
        <tr class="section-footer">
            <td></td>
            <td><strong>Skor Purata Bahagian A</strong></td>
            <td><strong>' . $teacherData['scores']['professionalism']['total'] . '</strong></td>
        </tr>
        <tr class="section-header">
            <td>B</td>
            <td class="category-header">Pengajaran Dan Pembelajaran</td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td>Pensyarah mempunyai pengetahuan yang luas tentang kursus yang diajar.</td>
            <td>' . $teacherData['scores']['teaching']['items']['knowledge'] . '</td>
        </tr>
        <tr>
            <td></td>
            <td>Saya faham isi kandungan kursus yang disampaikan oleh pensyarah.</td>
            <td>' . $teacherData['scores']['teaching']['items']['understanding'] . '</td>
        </tr>
        <tr>
            <td></td>
            <td>Bahan bantu mengajar pensyarah seperti nota / bahan aktiviti meningkatkan pemahaman saya.</td>
            <td>' . $teacherData['scores']['teaching']['items']['materials'] . '</td>
        </tr>
        <tr>
            <td></td>
            <td>Kaedah pengajaran pensyarah saya membantu saya memahami isi kandungan kursus.</td>
            <td>' . $teacherData['scores']['teaching']['items']['methods'] . '</td>
        </tr>
        <tr>
            <td></td>
            <td>Latihan/tugasan/kuiz yang dilaksanakan di dalam kelas oleh pensyarah menguji pemahaman saya.</td>
            <td>' . $teacherData['scores']['teaching']['items']['exercises'] . '</td>
        </tr>
        <tr>
            <td></td>
            <td>Pembelajaran dalam talian (online) yang dilaksanakan oleh pensyarah membantu proses pembelajaran saya.</td>
            <td>' . $teacherData['scores']['teaching']['items']['online_learning'] . '</td>
        </tr>
        <tr>
            <td></td>
            <td>Bahan PdP dalam talian dapat diakses dengan mudah.</td>
            <td>' . $teacherData['scores']['teaching']['items']['online_materials'] . '</td>
        </tr>
        <tr class="section-footer">
            <td></td>
            <td><strong>Skor Purata Bahagian B</strong></td>
            <td><strong>' . $teacherData['scores']['teaching']['total'] . '</strong></td>
        </tr>';
    
    // Only include English section if it's applicable
    if ($hasEnglish) {
        $html .= '
        <tr class="section-header">
            <td>C</td>
            <td class="category-header">Pelaksanaan PdP dalam Bahasa Inggeris</td>
            <td>' . $teacherData['scores']['english']['total'] . '</td>
        </tr>
        <tr>
            <td></td>
            <td>Pensyarah saya mengajar dalam bahasa Inggeris sepenuhnya.</td>
            <td>' . $teacherData['scores']['english']['items']['teaching'] . '</td>
        </tr>
        <tr>
            <td></td>
            <td>Pensyarah saya menyediakan bahan pengajaran dalam bahasa Inggeris.</td>
            <td>' . $teacherData['scores']['english']['items']['materials'] . '</td>
        </tr>
        <tr>
            <td></td>
            <td>Pensyarah mendorong saya membuat perbincangan dalam Bahasa Inggeris.</td>
            <td>' . $teacherData['scores']['english']['items']['discussion'] . '</td>
        </tr>
        <tr class="section-footer">
            <td></td>
            <td><strong>Skor Purata Bahagian C</strong></td>
            <td><strong>' . $teacherData['scores']['english']['total'] . '</strong></td>
        </tr>';
    } else {
        // Add note that English section is not applicable
        $html .= '
        <tr class="section-header">
            <td>C</td>
            <td class="category-header">Pelaksanaan PdP dalam Bahasa Inggeris</td>
            <td class="not-applicable">-</td>
        </tr>
        <tr>
            <td colspan="3" class="not-applicable">Bahagian ini tidak dinilai untuk kursus ini.</td>
        </tr>';
    }
    
    $html .= '
        <tr class="final-row">
            <td colspan="2" class="category-header">Skor Purata</td>
            <td>' . $overallScore . '</td>
        </tr>
        <tr class="final-row">
            <td colspan="2" class="category-header">Tahap Pencapaian Skor</td>
            <td>' . $achievementLevel . '</td>
        </tr>
    </table>
    
	 <div style="page-break-before: always;"></div>
	
    <div>
        <h3>Ulasan Keseluruhan/Cadangan Penambahbaikan</h3>
        <div class="comment-section">';
        
        if (isset($teacherData['comments']) && count($teacherData['comments']) > 0) {
            // Pick one random comment
            $randomIndex = array_rand($teacherData['comments']);
            $randomComment = $teacherData['comments'][$randomIndex];
            $html .= '<p>Pelajar: ' . htmlspecialchars($randomComment) . '</p>';
        } else {
            $html .= '<p>Tiada ulasan.</p>';
        }
        
        $html .= '
        </div>
    </div>
    
    <div>
        <table class="info-table2">
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