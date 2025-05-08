<?php
// Include TCPDF library (you'll need to download and install it)
// Download from: https://github.com/tecnickcom/TCPDF
require_once('tcpdf/tcpdf.php');

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
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['teacher']) && isset($_POST['data'])) {
    $selectedTeacher = $_POST['teacher'];
    $teacherData = json_decode($_POST['data'], true);
    
    // Get session, semester, and college average
    $semester = isset($_POST['semester']) ? $_POST['semester'] : '2';
    $session = isset($_POST['session']) ? $_POST['session'] : '2023/2024';
    $collegeAverage = isset($_POST['collegeAverage']) ? $_POST['collegeAverage'] : '9.9';
    
    // Check if teacher exists in data
    if (!isset($teacherData[$selectedTeacher])) {
        die("Teacher not found in data.");
    }
    
    // Get teacher data
    $data = $teacherData[$selectedTeacher];
    
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
    
    // Output PDF
    $pdf->Output('Teacher_Evaluation_' . str_replace(' ', '_', $data['name']) . '.pdf', 'I');
    exit;
} else {
    // If accessed directly without POST data
    header("Location: index.php");
    exit;
}

/**
 * Generate PDF content for a teacher
 * @param array $teacherData Processed teacher data
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
            font-size: 11pt;
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
        .info-table2 {
            width: 60%;
            margin-bottom: 20px;
            border: 0.5px solid #000;
        }
        .info-table2 td {
            border: 0.5px solid black;
            border-collapse: collapse;
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
            <td width="50%">Nama Pensyarah</td>
            <td width="50%">: ' . htmlspecialchars($teacherData['name']) . '</td>
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
    
    <table class="score-table">
        <tr>
            <th width="10%">BIL</th>
            <th width="70%">ITEM</th>
            <th width="19%">SKOR</th>
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
            <td></td>
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
            <td>-</td>
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
    
    <div class="page-break"></div>
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