<?php
// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$message = '';
$csvData = [];
$csvHeaders = [];
$questionCount = [
    'professionalism' => 0,
    'teaching' => 0, 
    'english' => 0,
    'total' => 0
];

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["csvFile"])) {
    $targetDir = "uploads/";
    
    // Create uploads directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $targetFile = $targetDir . basename($_FILES["csvFile"]["name"]);
    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    
    // Check if file is a CSV
    if ($fileType != "csv") {
        $message = '<div class="alert alert-danger">Sorry, only CSV files are allowed.</div>';
    } else {
        if (move_uploaded_file($_FILES["csvFile"]["tmp_name"], $targetFile)) {
            // Analyze the CSV file
            analyzeCSV($targetFile);
            $message = '<div class="alert alert-success">File analyzed successfully.</div>';
        } else {
            $message = '<div class="alert alert-danger">Sorry, there was an error uploading your file.</div>';
        }
    }
}

/**
 * Analyze CSV file structure
 * @param string $filePath Path to CSV file
 */
function analyzeCSV($filePath) {
    global $csvData, $csvHeaders, $questionCount;
    
    if (($handle = fopen($filePath, "r")) !== FALSE) {
        // Read header row
        $csvHeaders = fgetcsv($handle);
        
        // Read a few data rows for analysis
        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== FALSE && $rowCount < 5) {
            if (count($row) === count($csvHeaders)) {
                $csvData[] = array_combine($csvHeaders, $row);
            }
            $rowCount++;
        }
        
        // Count different types of questions
        foreach ($csvHeaders as $header) {
            if (strpos($header, 'Q04_A: Profesionalisme') !== false) {
                $questionCount['professionalism']++;
                $questionCount['total']++;
            } elseif (strpos($header, 'Q05_B: Pengajaran') !== false) {
                $questionCount['teaching']++;
                $questionCount['total']++;
            } elseif (strpos($header, 'Q06_C: Pelaksanaan PdP dalam BI') !== false) {
                $questionCount['english']++;
                $questionCount['total']++;
            }
        }
        
        fclose($handle);
    }
}

/**
 * Check if a column exists in the CSV
 * @param string $columnName Name of column to check
 * @return bool True if column exists
 */
function hasColumn($columnName) {
    global $csvHeaders;
    return in_array($columnName, $csvHeaders);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSV Structure Analyzer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
        }
        .container {
            max-width: 1000px;
        }
        .card {
            margin-bottom: 20px;
        }
        pre {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
        }
        .missing {
            color: #dc3545;
            font-weight: bold;
        }
        .present {
            color: #198754;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">CSV Structure Analyzer</h1>
        <p class="lead">Upload a CSV file to analyze its structure and identify potential issues.</p>
        
        <div class="card">
            <div class="card-header">
                Upload CSV File
            </div>
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="csvFile" class="form-label">Select CSV file</label>
                        <input class="form-control" type="file" id="csvFile" name="csvFile" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Analyze File</button>
                </form>
            </div>
        </div>
        
        <?php echo $message; ?>
        
        <?php if (!empty($csvData)): ?>
        <div class="card">
            <div class="card-header">
                CSV Analysis Results
            </div>
            <div class="card-body">
                <h5>Question Count Summary</h5>
                <ul>
                    <li>Professionalism Questions (A): <strong><?php echo $questionCount['professionalism']; ?></strong></li>
                    <li>Teaching & Learning Questions (B): <strong><?php echo $questionCount['teaching']; ?></strong></li>
                    <li>English Questions (C): <strong><?php echo $questionCount['english']; ?></strong></li>
                    <li>Total Evaluation Questions: <strong><?php echo $questionCount['total']; ?></strong></li>
                </ul>
                
                <h5 class="mt-4">Key Column Check</h5>
                <p>The following required columns were checked:</p>
                <ul>
                    <li>Teacher Name (Q01_Nama Pensyarah): 
                        <?php if (hasColumn('Q01_Nama Pensyarah')): ?>
                            <span class="present">Present</span>
                        <?php else: ?>
                            <span class="missing">Missing!</span>
                        <?php endif; ?>
                    </li>
                    <li>Course Code (Q03_Kod Subjek): 
                        <?php if (hasColumn('Q03_Kod Subjek')): ?>
                            <span class="present">Present</span>
                        <?php else: ?>
                            <span class="missing">Missing!</span>
                        <?php endif; ?>
                    </li>
                    <li>Comments (Q07_Ulasan): 
                        <?php if (hasColumn('Q07_Ulasan')): ?>
                            <span class="present">Present</span>
                        <?php else: ?>
                            <span class="missing">Missing!</span>
                        <?php endif; ?>
                    </li>
                </ul>
                
                <h5 class="mt-4">CSV Headers</h5>
                <p>Your CSV contains the following columns:</p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Column Name</th>
                                <th>Category</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($csvHeaders as $index => $header): 
                                $category = "Other";
                                if (strpos($header, 'Q04_A: Profesionalisme') !== false) {
                                    $category = "Professionalism (A)";
                                } elseif (strpos($header, 'Q05_B: Pengajaran') !== false) {
                                    $category = "Teaching & Learning (B)";
                                } elseif (strpos($header, 'Q06_C: Pelaksanaan PdP dalam BI') !== false) {
                                    $category = "English (C)";
                                } elseif ($header == 'Q01_Nama Pensyarah') {
                                    $category = "Teacher Information";
                                } elseif ($header == 'Q03_Kod Subjek') {
                                    $category = "Course Information";
                                } elseif ($header == 'Q07_Ulasan') {
                                    $category = "Comments";
                                }
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($header); ?></td>
                                <td><?php echo $category; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <h5 class="mt-4">Sample Data (First Row)</h5>
                <pre><?php if (!empty($csvData[0])) print_r($csvData[0]); ?></pre>
                
                <h5 class="mt-4">Recommendations</h5>
                <div class="alert alert-info">
                    <?php if ($questionCount['total'] < 13): ?>
                    <p><strong>Your CSV contains fewer than the standard 13 evaluation questions.</strong></p>
                    <p>This is acceptable but requires using the flexible data processing function. Please modify your code to use the flexible version that supports variable question counts.</p>
                    <?php else: ?>
                    <p>Your CSV appears to have the standard structure with 13 evaluation questions.</p>
                    <?php endif; ?>
                    
                    <?php if (!hasColumn('Q01_Nama Pensyarah') || !hasColumn('Q03_Kod Subjek')): ?>
                    <p class="text-danger"><strong>Required columns are missing!</strong> The application requires "Q01_Nama Pensyarah" and "Q03_Kod Subjek" columns to function correctly.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="mt-3">
            <a href="index.php" class="btn btn-secondary">Back to Home</a>
        </div>
    </div>
</body>
</html>