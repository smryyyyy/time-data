<?php
namespace App\Controllers;

/**
 * 日志查看
 */
class LogController
{
    public function index(): void
    {
        $logger   = $GLOBALS['hermes_logger'];
        $dates    = $logger->listDates();
        $selected = $_GET['date'] ?? date('Y-m-d');
        $selected = $this->validateDate($selected);
        $logText  = $logger->get($selected);

        render('logs', compact('dates', 'selected', 'logText'));
    }

    public function view(): void
    {
        $logger   = $GLOBALS['hermes_logger'];
        $date     = $_GET['date'] ?? date('Y-m-d');
        $date     = $this->validateDate($date);
        $logText  = $logger->get($date);

        header('Content-Type: text/plain; charset=utf-8');
        echo $logText ?: '(空)';
    }

    private function validateDate(string $date): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        return date('Y-m-d'); // 格式不合法则回退到今天
    }
}
