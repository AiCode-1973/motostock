<?php
declare(strict_types=1);

namespace App\Controllers\Main;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

final class HomeController extends Controller
{
    public function index(Request $r): Response
    {
        $plans = $this->db()->fetchAll('SELECT * FROM plans WHERE active = 1 AND is_public = 1 ORDER BY sort_order, price_cents');
        return $this->view('main/home', ['plans' => $plans, 'title' => 'Gestão e loja virtual para peças de moto']);
    }
}
