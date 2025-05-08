<?php
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
    
    // Calculate overall average score
    $overallScore = round(
        ($data['scores']['professionalism']['total'] + 
         $data['scores']['teaching']['total'] + 
         $data['scores']['english']['total']) / 3,
        1
    );
    
    // Get achievement level
    function getAchievementLevel($score) {
        if ($score >= 9.0) return "CEMERLANG";
        if ($score >= 7.0) return "BAIK";
        if ($score >= 5.0) return "SEDERHANA";
        if ($score >= 3.0) return "LEMAH";
        return "SANGAT LEMAH";
    }
    
    $achievementLevel = getAchievementLevel($overallScore);
    
    // Get college average (placeholder - in a real app this would come from database)
    $collegeAverage = 9.9;
    
    // Output HTML preview
    echo '<h4 class="text-center">LAPORAN INDIVIDU PENILAIAN ePP PENSYARAH KMPP</h4>';
    echo '<h5 class="text-center">SEMESTER ' . $semester . ' SESI ' . $session . '</h5>';
    
    echo '<p>Information:</p>';
    echo '<ul>';
    echo '<li>Nama Pensyarah: ' . htmlspecialchars($data['name']) . '</li>';
    echo '<li>Kursus: ' . htmlspecialchars($data['course']) . '</li>';
    echo '<li>Skor Purata: ' . $overallScore . '</li>';
    echo '<li>Skor Purata Kolej: ' . $collegeAverage . '</li>';
    echo '<li>Tahap Pencapaian: ' . $achievementLevel . '</li>';
    echo '<li>Bilangan Respon: ' . $data['responses'] . '</li>';
    echo '</ul>';
    
    echo '<p>Preview report generated. Use the "Generate PDF" button to create the full report.</p>';
} else {
    // If accessed directly without POST data
    echo "Invalid request.";
}
?>