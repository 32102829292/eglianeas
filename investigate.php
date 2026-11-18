<?php
require 'vendor/autoload.php';

$r = new ReflectionClass('App\Http\Controllers\Admin\ClientController');
echo 'Namespace: App\Http\Controllers\Admin' . PHP_EOL;
echo 'Has viewCertificateOfRegistration: ' . ($r->hasMethod('viewCertificateOfRegistration') ? 'yes' : 'no') . PHP_EOL;
if ($r->hasMethod('viewCertificateOfRegistration')) {
    $m = $r->getMethod('viewCertificateOfRegistration');
    echo 'Method: ' . $m->getName() . PHP_EOL;
    echo 'Visibility: ' . ($m->isPublic() ? 'public' : ($m->isProtected() ? 'protected' : 'private')) . PHP_EOL;
}
"