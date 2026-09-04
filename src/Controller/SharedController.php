<?php

namespace Drupal\hotel_reservation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SharedController extends ControllerBase {

  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

  protected $entityTypeManager;
  protected $dateFormatter;

  protected function checkAccess(Request $request, string $token) {
    $config = \Drupal::config('hotel_reservation.settings');
    if (!$config->get('share_enabled')) {
      throw new NotFoundHttpException();
    }
    $stored = (string) $config->get('share_token');
    if ($stored === '' || !hash_equals($stored, $token)) {
      throw new NotFoundHttpException();
    }
    $password = (string) $config->get('share_password');
    if ($password === '') {
      return TRUE;
    }
    $session = $request->getSession();
    $key = 'hr_share_auth_' . $token;
    if ($session->get($key)) {
      return TRUE;
    }
    return FALSE;
  }

  protected function buildLoginForm(string $token, string $currentUrl, bool $error = FALSE): array {
    $msg = $error ? '<p style="color:#dc2626;margin-bottom:12px;">Неверный пароль</p>' : '';
    $html = '<div class="page-content-section"><div class="hr-share-login">';
    $html .= '<h2>Доступ ограничен</h2>';
    $html .= '<p>Введите пароль для просмотра статистики.</p>';
    $html .= $msg;
    $html .= '<form method="POST" action="' . htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8') . '">';
    $html .= '<input type="password" name="password" placeholder="Пароль" required class="hr-share-login__input">';
    $html .= '<button type="submit" class="hr-share-login__btn">Войти</button>';
    $html .= '</form></div></div>';

    return [
      '#markup' => $html,
      '#allowed_tags' => ['div', 'h2', 'p', 'form', 'input', 'button', 'span'],
      '#attached' => [
        'library' => ['hotel_reservation/shared'],
        'html_head' => [
          [['#tag' => 'meta', '#attributes' => ['name' => 'robots', 'content' => 'noindex, nofollow, noarchive']], 'robots_noindex'],
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  protected function handleAuth(Request $request, string $token) {
    if (\Drupal::currentUser()->hasPermission('administer hotel reservation')) {
      return NULL;
    }
    $config = \Drupal::config('hotel_reservation.settings');
    $password = (string) $config->get('share_password');
    if ($password === '') {
      return NULL;
    }
    $session = $request->getSession();
    $key = 'hr_share_auth_' . $token;
    if ($session->get($key)) {
      return NULL;
    }
    if ($request->isMethod('POST') && $request->request->has('password')) {
      $input = (string) $request->request->get('password');
      $ok = FALSE;
      if ($password !== '' && password_get_info($password)['algo']) {
        $ok = password_verify($input, $password);
      }
      else {
        $ok = hash_equals($password, $input);
      }
      if ($ok) {
        $session->set($key, TRUE);
        return new RedirectResponse($request->getRequestUri());
      }
      else {
        return $this->buildLoginForm($token, $request->getRequestUri(), TRUE);
      }
    }
    return $this->buildLoginForm($token, $request->getRequestUri(), FALSE);
  }

  protected function addNoindex(array $build): array {
    $build['#attached']['html_head'][] = [['#tag' => 'meta', '#attributes' => ['name' => 'robots', 'content' => 'noindex, nofollow, noarchive']], 'robots_noindex'];
    $build['#cache']['max-age'] = 0;
    $build['#cache']['contexts'][] = 'session';
    $build['#cache']['contexts'][] = 'url';
    $build['#prefix'] = '<div class="page-content-section">';
    $build['#suffix'] = '</div>';
    return $build;
  }

  public function dashboard(Request $request, string $token) {
    $isAdmin = \Drupal::currentUser()->hasPermission('administer hotel reservation');
    $config = \Drupal::config('hotel_reservation.settings');
    if (!$isAdmin) {
      if (!$config->get('share_enabled') || !hash_equals((string) $config->get('share_token'), $token)) {
        throw new NotFoundHttpException();
      }
      $auth = $this->handleAuth($request, $token);
      if ($auth instanceof RedirectResponse) {
        return $auth;
      }
      if (is_array($auth)) {
        return $auth;
      }
    }

    $controller = \Drupal::classResolver()->getInstanceFromDefinition(DashboardController::class);
    $build = $controller->dashboard();
    $build = $this->addNoindex($build);
    $build['#attached']['library'][] = 'hotel_reservation/admin-styles';
    $build['#cache']['contexts'][] = 'session';
    return $build;
  }

  public function analytics(Request $request, string $token) {
    $isAdmin = \Drupal::currentUser()->hasPermission('administer hotel reservation');
    $config = \Drupal::config('hotel_reservation.settings');
    if (!$isAdmin) {
      if (!$config->get('share_enabled') || !hash_equals((string) $config->get('share_token'), $token)) {
        throw new NotFoundHttpException();
      }
      $auth = $this->handleAuth($request, $token);
      if ($auth instanceof RedirectResponse) {
        return $auth;
      }
      if (is_array($auth)) {
        return $auth;
      }
    }
    $controller = \Drupal::classResolver()->getInstanceFromDefinition(AnalyticsController::class);
    $build = $controller->analytics();
    $build = $this->addNoindex($build);
    $build['#cache']['contexts'][] = 'session';
    return $build;
  }

  public function calendar(Request $request, string $token, $month = NULL, $year = NULL) {
    $isAdmin = \Drupal::currentUser()->hasPermission('administer hotel reservation');
    $config = \Drupal::config('hotel_reservation.settings');
    if (!$isAdmin) {
      if (!$config->get('share_enabled') || !hash_equals((string) $config->get('share_token'), $token)) {
        throw new NotFoundHttpException();
      }
      $auth = $this->handleAuth($request, $token);
      if ($auth instanceof RedirectResponse) {
        return $auth;
      }
      if (is_array($auth)) {
        return $auth;
      }
    }
    $controller = \Drupal::classResolver()->getInstanceFromDefinition(HotelReservationController::class);
    $build = $controller->calendar($month, $year);
    $build = $this->addNoindex($build);
    if (isset($build['#prev_url']) && isset($build['#next_url'])) {
      $build['#prev_url'] = Url::fromRoute('hotel_reservation.share_calendar_month', ['token' => $token, 'month' => $this->extractMonth($build['#prev_url']), 'year' => $this->extractYear($build['#prev_url'])])->toString();
      $build['#next_url'] = Url::fromRoute('hotel_reservation.share_calendar_month', ['token' => $token, 'month' => $this->extractMonth($build['#next_url']), 'year' => $this->extractYear($build['#next_url'])])->toString();
      $build['#current_url'] = Url::fromRoute('hotel_reservation.share_calendar', ['token' => $token])->toString();
    }
    if (!empty($build['#month_selector'])) {
      foreach ($build['#month_selector'] as &$opt) {
        $opt['url'] = Url::fromRoute('hotel_reservation.share_calendar_month', ['token' => $token, 'month' => $opt['month'], 'year' => $opt['year']])->toString();
      }
      unset($opt);
    }
    $build['#cache']['contexts'][] = 'session';
    return $build;
  }

  protected function extractMonth(string $url): int {
    if (preg_match('#/calendar/(\d+)/\d+#', $url, $m)) {
      return (int) $m[1];
    }
    return (int) date('n');
  }

  protected function extractYear(string $url): int {
    if (preg_match('#/calendar/\d+/(\d+)#', $url, $m)) {
      return (int) $m[1];
    }
    return (int) date('Y');
  }

  public function shareInfo() {
    $config = \Drupal::config('hotel_reservation.settings');
    $enabled = (bool) $config->get('share_enabled');
    $token = (string) $config->get('share_token');
    $hasPassword = (string) $config->get('share_password') !== '';
    $base = \Drupal::request()->getSchemeAndHttpHost();

    if (!$enabled || $token === '') {
      $html = '<div style="padding:16px;background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;margin-bottom:16px;">';
      $html .= '<strong>Общий доступ отключён.</strong> Включите его в <a href="' . Url::fromRoute('hotel_reservation.settings')->toString() . '">Настройках</a> — секция «Доступ для клиента».';
      $html .= '</div>';
      $html .= '<p>После включения здесь появятся ссылки вида <code>/hotel-reservation/share/{token}/dashboard</code> и т.д., защищённые паролем и закрытые от индексации (<code>noindex,nofollow</code>).</p>';
      return [
        '#markup' => $html,
        '#allowed_tags' => ['div', 'strong', 'a', 'p', 'code'],
        '#cache' => ['max-age' => 0],
      ];
    }

    $urls = [
      'dashboard' => $base . '/hotel-reservation/share/' . $token . '/dashboard',
      'analytics' => $base . '/hotel-reservation/share/' . $token . '/analytics',
      'calendar' => $base . '/hotel-reservation/share/' . $token . '/calendar',
      'calendar_sample' => $base . '/hotel-reservation/share/' . $token . '/calendar/' . date('n') . '/' . date('Y'),
    ];

    $html = '<div style="padding:16px;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;margin-bottom:16px;">';
    $html .= '<strong>Доступ включён.</strong> Токен: <code>' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '</code> · Пароль: ' . ($hasPassword ? 'установлен' : 'не установлен (только секретная ссылка)') . ' · Страницы закрыты от индексации <code>noindex,nofollow,noarchive</code>.';
    $html .= '</div>';

    $html .= '<table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">';
    $html .= '<thead><tr style="background:#f9fafb;text-align:left;"><th style="padding:10px 14px;border-bottom:1px solid #e5e7eb;">Страница</th><th style="padding:10px 14px;border-bottom:1px solid #e5e7eb;">Ссылка</th></tr></thead><tbody>';
    $rows = [
      ['Панель', $urls['dashboard']],
      ['Аналитика', $urls['analytics']],
      ['Календарь (текущий месяц)', $urls['calendar']],
      ['Календарь (пример 9/2026)', $urls['calendar_sample']],
    ];
    foreach ($rows as [$label, $url]) {
      $html .= '<tr><td style="padding:10px 14px;border-bottom:1px solid #f3f4f6;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>';
      $html .= '<td style="padding:10px 14px;border-bottom:1px solid #f3f4f6;"><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" style="word-break:break-all;">' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</a></td></tr>';
    }
    $html .= '</tbody></table>';
    $html .= '<p style="margin-top:12px;color:#6b7280;font-size:13px;">Ссылки требуют токен в URL' . ($hasPassword ? ' и пароль' : '') . '. Отправьте их клиенту. Изменить токен/пароль можно в <a href="' . Url::fromRoute('hotel_reservation.settings')->toString() . '">Настройках</a>.</p>';

    return [
      '#markup' => $html,
      '#allowed_tags' => ['div', 'strong', 'a', 'p', 'code', 'table', 'thead', 'tbody', 'tr', 'th', 'td'],
      '#cache' => ['max-age' => 0],
    ];
  }

}
