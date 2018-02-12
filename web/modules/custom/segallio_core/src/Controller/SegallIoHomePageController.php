<?php

namespace Drupal\segallio_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Class SegallIoHomePageController.
 */
class SegallIoHomePageController extends ControllerBase {

  /**
   * Home page.
   *
   * @return array
   *   Return Hello string.
   */
  public function homePage() {
    $url = Url::fromRoute('segallio_restful.all_entries', [], ['absolute' => TRUE])->toString();
    return [
      '#theme' => 'homepage',
      '#attached' => [
        'library' => [
          'core/drupalSettings',
          'segallio_theme/vue',
          'segallio_theme/vueHttp',
          'segallio_theme/timeline',
        ],
        'drupalSettings' => ['entries_base' => $url],
      ],
    ];
  }

}
