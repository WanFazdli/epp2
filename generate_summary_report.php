<?php
// Include TCPDF library
require_once('tcpdf/tcpdf.php');

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    
    // Generate PDF
    try {
        // Create new PDF document
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false); // Landscape mode
        
        // Set document information
        $pdf->SetCreator('Teacher Evaluation System');
        $pdf->SetAuthor('Kolej Matrikulasi Pulau Pinang');
        $pdf->SetTitle('Teacher Evaluation Summary Report');
        $pdf->SetSubject('Teacher Evaluation Summary');
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set margins
        $pdf->SetMargins(10, 10, 10);
        
        // Set auto page breaks
        $pdf->SetAutoPageBreak(true, 10);
        
        // Set font
        $pdf->SetFont('helvetica', '', 8);
        
        // Add a page
        $pdf->AddPage();
        
        // Set title
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'LAPORAN RINGKASAN PENILAIAN ePP PENSYARAH KMPP SEMESTER ' . $semester . ' SESI ' . $session, 0, 1, 'C');
        $pdf->Ln(5);
        
        // Prepare data for table
        $html = '<table border="1" cellpadding="3" cellspacing="0" style="font-size:7pt; border-collapse: collapse; width: 100%;">
            <thead>
                <tr style="background-color: #f2f2f2; font-weight: bold; text-align: center;">
                    <th width="15%">Nama Pensyarah</th>
                    <th width="4%">Bilangan Pelajar</th>
                    <th width="4%">A1</th>
                    <th width="4%">A2</th>
                    <th width="4%">A3</th>
                    <th width="4%">B4</th>
                    <th width="4%">B5</th>
                    <th width="4%">B6</th>
                    <th width="4%">B7</th>
                    <th width="4%">B8</th>
                    <th width="4%">B9</th>
                    <th width="4%">B10</th>
                    <th width="4%">C11</th>
                    <th width="4%">C12</th>
                    <th width="4%">C13</th>
                    <th width="5%">Purata A</th>
                    <th width="5%">Purata B</th>
                    <th width="5%">Purata C</th>
                    <th width="5%">Purata Keseluruhan</th>
                    <th width="9%">Tahap Pencapaian</th>
                </tr>
            </thead>
            <tbody>';
        
        // Sort teachers by overall average score (descending)
        uasort($teacherData, function($a, $b) {
            $avgA = ($a['scores']['professionalism']['total'] + $a['scores']['teaching']['total'] + $a['scores']['english']['total']) / 3;
            $avgB = ($b['scores']['professionalism']['total'] + $b['scores']['teaching']['total'] + $b['scores']['english']['total']) / 3;
            return $avgB <=> $avgA; // Descending order
        });
        
        // Add data rows
        foreach ($teacherData as $name => $data) {
            // Calculate averages
            $avgA = round($data['scores']['professionalism']['total'], 1);
            $avgB = round($data['scores']['teaching']['total'], 1);
            $avgC = round($data['scores']['english']['total'], 1);
            $avgAll = round(($avgA + $avgB + $avgC) / 3, 1);
            
            // Get achievement level
            $achievementLevel = getAchievementLevel($avgAll);
            
            // Format row style based on achievement level
            $rowStyle = '';
            if ($achievementLevel == 'CEMERLANG') {
                $rowStyle = 'background-color: #e6ffe6;'; // Light green for Excellent
            } elseif ($achievementLevel == 'BAIK') {
                $rowStyle = 'background-color: #ffffcc;'; // Light yellow for Good
            }
            
            // Add row to table
            $html .= '<tr style="' . $rowStyle . '">
                <td>' . htmlspecialchars($name) . '</td>
                <td align="center">' . $data['responses'] . '</td>
                <td align="center">' . $data['scores']['professionalism']['items']['appearance'] . '</td>
                <td align="center">' . $data['scores']['professionalism']['items']['punctuality'] . '</td>
                <td align="center">' . $data['scores']['professionalism']['items']['care'] . '</td>
                <td align="center">' . $data['scores']['teaching']['items']['knowledge'] . '</td>
                <td align="center">' . $data['scores']['teaching']['items']['understanding'] . '</td>
                <td align="center">' . $data['scores']['teaching']['items']['materials'] . '</td>
                <td align="center">' . $data['scores']['teaching']['items']['methods'] . '</td>
                <td align="center">' . $data['scores']['teaching']['items']['exercises'] . '</td>
                <td align="center">' . $data['scores']['teaching']['items']['online_learning'] . '</td>
                <td align="center">' . $data['scores']['teaching']['items']['online_materials'] . '</td>
                <td align="center">' . $data['scores']['english']['items']['teaching'] . '</td>
                <td align="center">' . $data['scores']['english']['items']['materials'] . '</td>
                <td align="center">' . $data['scores']['english']['items']['discussion'] . '</td>
                <td align="center"><strong>' . $avgA . '</strong></td>
                <td align="center"><strong>' . $avgB . '</strong></td>
                <td align="center"><strong>' . $avgC . '</strong></td>
                <td align="center"><strong>' . $avgAll . '</strong></td>
                <td align="center"><strong>' . $achievementLevel . '</strong></td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
        
        // Add legend
        $html .= '<br><br><table border="1" cellpadding="3" style="font-size:8pt; width: 200px;">
            <tr style="background-color: #f2f2f2; font-weight: bold;">
                <th>Julat Skor</th>
                <th>Tahap Pencapaian</th>
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
        </table>';
        
        // Add key for column headers
        $html .= '<br><p><strong>Keterangan Kolum:</strong></p>
        <table border="0" cellpadding="1" style="font-size:7pt; width: 100%;">
            <tr>
                <td width="25%"><strong>A1:</strong> Penampilan pensyarah menarik minat</td>
                <td width="25%"><strong>A2:</strong> Pensyarah menepati waktu</td>
                <td width="25%"><strong>A3:</strong> Pensyarah mengambil berat</td>
                <td width="25%"><strong>B4:</strong> Pensyarah berpengetahuan luas</td>
            </tr>
            <tr>
                <td><strong>B5:</strong> Saya faham isi kandungan kursus</td>
                <td><strong>B6:</strong> Bahan bantu mengajar membantu</td>
                <td><strong>B7:</strong> Kaedah pengajaran membantu</td>
                <td><strong>B8:</strong> Latihan/tugasan/kuiz menguji</td>
            </tr>
            <tr>
                <td><strong>B9:</strong> Pembelajaran dalam talian membantu</td>
                <td><strong>B10:</strong> Bahan PdP mudah diakses</td>
                <td><strong>C11:</strong> Mengajar dalam B. Inggeris</td>
                <td><strong>C12:</strong> Bahan pengajaran dalam B. Inggeris</td>
            </tr>
            <tr>
                <td><strong>C13:</strong> Mendorong perbincangan B. Inggeris</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        </table>';
        
        // Add footer
        $html .= '<br><br>
        <p style="text-align: right;">Tarikh: ' . date('d/m/Y') . '</p>
        <p style="text-align: center;">Laporan ini dijana oleh Sistem Penilaian ePP Pensyarah KMPP</p>
        <p style="text-align: center;">Develop by OneExa EduTech Solution for KMPP</p>';
        
        // Output HTML content
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Output PDF
        $pdf->Output('Teacher_Evaluation_Summary_Report.pdf', 'I');
        exit;
    } catch (Exception $e) {
        die("Error generating PDF: " . $e->getMessage());
    }
} else {
    // If accessed directly without POST data
    header("Location: index.php");
    exit;
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
?>