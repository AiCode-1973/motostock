<?php
// Gera uma APP_KEY nova. Guarde-a com cuidado: sem ela, segredos cifrados (MFA, credenciais) não podem ser lidos.
echo 'APP_KEY=base64:' . base64_encode(random_bytes(32)) . PHP_EOL;
