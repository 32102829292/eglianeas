<?php
$routes = file('routes/web.php');
$inWorkPlan = false;
foreach ($routes as $i => $line) {
    if (stripos($line, 'work-plan') !== false || stripos($line, 'WorkPlan') !== false) {
        $inWorkPlan = true;
    }
    if ($inWorkPlan) {
        echo ($i+1) . ': ' . $line . PHP_EOL;
        if (stripos($line, '});') !== false && !stripos($line, '//')) {
            // Check if we're at the end of the work-plan group
            $inWorkPlan = false;
        }
    }
}