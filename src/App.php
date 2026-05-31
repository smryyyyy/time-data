<?php
namespace App;

class App
{
    private array $config;
    private Router $router;
    private Logger $logger;

    public function __construct(array $config)
    {
        $this->config = $config;
        date_default_timezone_set($config['timezone'] ?? 'Asia/Shanghai');
        $this->logger = new Logger($config['log_dir'], $config['alert_webhook'] ?? '');
        $this->router = new Router();
        $this->registerRoutes();
        $GLOBALS['hermes_config'] = $config;
        $GLOBALS['hermes_logger'] = $this->logger;
    }

    private function registerRoutes(): void
    {
        $r = $this->router;

        // 无需登录
        $r->get('/login',     'Controllers\AuthController', 'login');
        $r->post('/login',    'Controllers\AuthController', 'doLogin');
        $r->get('/logout',    'Controllers\AuthController', 'logout');
        $r->get('/cron/tick', 'Controllers\CronController', 'tick');

        // 图床（token 鉴权）
        $r->delete('/api/images/{name:\S+}',   'Controllers\ImageController', 'delete');

        // 需要登录
        $r->get('/',                      'Controllers\DashboardController',  'index',  true);
        $r->post('/run',                  'Controllers\DashboardController',  'run',    true);
        $r->get('/logs',                  'Controllers\LogController',        'index',  true);
        $r->get('/logs/view',             'Controllers\LogController',        'view',   true);
        $r->get('/settings',             'Controllers\SettingsController',   'index',  true);
        $r->post('/settings',            'Controllers\SettingsController',   'save',   true);
        $r->get('/templates',            'Controllers\\TemplateController',   'index',  true);
        $r->post('/templates/upload',    'Controllers\\TemplateController',   'upload', true);
    }

    public function run(): void
    {
        $this->router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] ?? '/');
    }
}
