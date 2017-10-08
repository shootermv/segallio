<?php

namespace Drupal\segallio_core\EventSubscriber;

use Drupal\segallio_facebook\SegallIOFacebook;
use Drupal\segallio_instagram\SegallIoInstagram;
use Drupal\segallio_twitter\SegallIoTwitter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class SegallIoCoreEventSubscriber.
 */
class SegallIoCoreEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('onRequest', -100);

    return $events;
  }

  /**
   * Initializes bargain core module requirements.
   */
  public function onRequest(GetResponseEvent $event) {
    $posts = SegallIoInstagram::getApi();

    dpm($posts->getPosts());
  }

}
