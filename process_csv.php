$avgC = $data['scores']['english']['total'];
                            $overall = round(($avgA + $avgB + $avgC) / 3, 1);
                            
                            $unitAvgA += $avgA;
                            $unitAvgB += $avgB;
                            $unitAvgC += $avgC;
                            $unitOverall += $overall;
                            $totalStudents += $data['responses'];
                        }
                        
                        $unitAvgA = round($unitAvgA / $totalTeachers, 1);
                        $unitAvgB = round($unitAvgB / $totalTeachers, 1);
                        $unitAvgC = round($unitAvgC / $totalTeachers, 1);
                        $unitOverall = round(($unitAvgA + $unitAvgB + $unitAvgC) / 3, 1);
                        $unitAchievement = getAchievementLevel($unitOverall);
                        
                        // Get color based on achievement
                        $achievementColors = [
                            'CEMERLANG' => '#28a745',
                            'BAIK' => '#17a2b8',
                            'SEDERHANA' => '#ffc107',
                            'LEMAH' => '#fd7e14',
                            'SANGAT LEMAH' => '#dc3545'
                        ];
                        $unitColor = isset($achievementColors[$unitAchievement]) ? $achievementColors[$unitAchievement] : '#6c757d';
                        
                        $unitId = 'unit-' . preg_replace('/[^a-zA-Z0-9]/', '-', $unit);
                    ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading-<?php echo $unitId; ?>">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $unitId; ?>" aria-expanded="false" aria-controls="collapse-<?php echo $unitId; ?>">
                                <span style="display: flex; justify-content: space-between; width: 100%;">
                                    <span><strong><?php echo htmlspecialchars($unit); ?></strong> (<?php echo $totalTeachers; ?> teachers)</span>
                                    <span class="badge rounded-pill ms-2" style="background-color: <?php echo $unitColor; ?>;"><?php echo $unitOverall; ?> - <?php echo $unitAchievement; ?></span>
                                </span>
                            </button>
                        </h2>
                        <div id="collapse-<?php echo $unitId; ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?php echo $unitId; ?>" data-bs-parent="#unitAccordion">
                            <div class="accordion-body">
                                <div class="unit-header">
                                    <div class="row">
                                        <div class="col-md-9">
                                            <h5><?php echo htmlspecialchars($unit); ?> Unit</h5>
                                            <p><strong>Total Teachers:</strong> <?php echo $totalTeachers; ?> | <strong>Total Students:</strong> <?php echo $totalStudents; ?></p>
                                        </div>
                                        <div class="col-md-3 text-end">
                                            <form action="generate_unit_reports.php" method="post">
                                                <input type="hidden" name="data" value="<?php echo htmlspecialchars(json_encode($processedData)); ?>">
                                                <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester); ?>">
                                                <input type="hidden" name="session" value="<?php echo htmlspecialchars($session); ?>">
                                                <input type="hidden" name="collegeAverage" value="<?php echo htmlspecialchars($collegeAverage); ?>">
                                                <input type="hidden" name="unitMappings" value="<?php echo htmlspecialchars(json_encode($unitMappings)); ?>">
                                                <input type="hidden" name="singleUnit" value="<?php echo htmlspecialchars($unit); ?>">
                                                <button type="submit" class="btn btn-sm btn-warning">Generate Unit Report</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <h6 class="card-title text-muted">Professionalism</h6>
                                                <h3 style="color: #fd7e14;"><?php echo $unitAvgA; ?></h3>
                                                <small>Section A</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <h6 class="card-title text-muted">Teaching</h6>
                                                <h3 style="color: #17a2b8;"><?php echo $unitAvgB; ?></h3>
                                                <small>Section B</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <h6 class="card-title text-muted">English</h6>
                                                <h3 style="color: #28a745;"><?php echo $unitAvgC; ?></h3>
                                                <small>Section C</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <h6 class="card-title text-muted">Overall</h6>
                                                <h3 style="color: <?php echo $unitColor; ?>;"><?php echo $unitOverall; ?></h3>
                                                <small><span class="badge" style="background-color: <?php echo $unitColor; ?>;"><?php echo $unitAchievement; ?></span></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Teacher</th>
                                                <th>Course</th>
                                                <th class="text-center">Students</th>
                                                <th class="text-center">Prof.</th>
                                                <th class="text-center">Teaching</th>
                                                <th class="text-center">English</th>
                                                <th class="text-center">Overall</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($teachers as $name => $data): 
                                                $avgA = $data['scores']['professionalism']['total'];
                                                $avgB = $data['scores']['teaching']['total'];
                                                $avgC = $data['scores']['english']['total'];
                                                $overall = round(($avgA + $avgB + $avgC) / 3, 1);
                                                $achievement = getAchievementLevel($overall);
                                                $teacherColor = isset($achievementColors[$achievement]) ? $achievementColors[$achievement] : '#6c757d';
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($name); ?></td>
                                                <td><?php echo htmlspecialchars($data['course']); ?></td>
                                                <td class="text-center"><?php echo $data['responses']; ?></td>
                                                <td class="text-center"><?php echo $avgA; ?></td>
                                                <td class="text-center"><?php echo $avgB; ?></td>
                                                <td class="text-center"><?php echo $avgC; ?></td>
                                                <td class="text-center"><span class="badge rounded-pill" style="background-color: <?php echo $teacherColor; ?>;"><?php echo $overall; ?></span></td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm">
                                                        <form action="generate_pdf.php" method="post" target="_blank" style="display: inline;">
                                                            <input type="hidden" name="teacher" value="<?php echo htmlspecialchars($name); ?>">
                                                            <input type="hidden" name="data" value="<?php echo htmlspecialchars(json_encode($processedData)); ?>">
                                                            <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester); ?>">
                                                            <input type="hidden" name="session" value="<?php echo htmlspecialchars($session); ?>">
                                                            <input type="hidden" name="collegeAverage" value="<?php echo htmlspecialchars($collegeAverage); ?>">
                                                            <input type="hidden" name="unitMappings" value="<?php echo htmlspecialchars(json_encode($unitMappings)); ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Generate PDF"><i class="bi bi-file-pdf"></i></button>
                                                        </form>
                                                        <form action="teacher_dashboard.php" method="post" target="_blank" style="display: inline;">
                                                            <input type="hidden" name="teacher" value="<?php echo htmlspecialchars($name); ?>">
                                                            <input type="hidden" name="data" value="<?php echo htmlspecialchars(json_encode($processedData)); ?>">
                                                            <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester); ?>">
                                                            <input type="hidden" name="session" value="<?php echo htmlspecialchars($session); ?>">
                                                            <input type="hidden" name="collegeAverage" value="<?php echo htmlspecialchars($collegeAverage); ?>">
                                                            <input type="hidden" name="unitMappings" value="<?php echo htmlspecialchars(json_encode($unitMappings)); ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-success" title="View Dashboard"><i class="bi bi-speedometer2"></i></button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add button functionality for unit mappings
        document.addEventListener('DOMContentLoaded', function() {
            const addUnitBtn = document.querySelector('.add-unit-mapping');
            if (addUnitBtn) {
                const unitMappingsRows = document.getElementById('unitMappingsRows');
                
                addUnitBtn.addEventListener('click', function() {
                    const newRow = document.createElement('div');
                    newRow.className = 'row mb-2';
                    newRow.innerHTML = `
                        <div class="col-md-3">
                            <input type="text" class="form-control form-control-sm" name="unitPrefix[]" placeholder="Course Prefix (e.g., WE)">
                        </div>
                        <div class="col-md-8">
                            <input type="text" class="form-control form-control-sm" name="unitName[]" placeholder="Unit Name (e.g., English Department)">
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-sm btn-danger remove-unit-mapping"><i class="bi bi-trash"></i></button>
                        </div>
                    `;
                    unitMappingsRows.appendChild(newRow);
                    
                    // Add event listener to the remove button
                    newRow.querySelector('.remove-unit-mapping').addEventListener('click', function() {
                        unitMappingsRows.removeChild(newRow);
                    });
                });
            }
        });
    </script>
</body>
</html>