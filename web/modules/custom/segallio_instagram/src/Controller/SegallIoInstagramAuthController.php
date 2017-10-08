<?php

namespace Drupal\segallio_instagram\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_auth\SocialAuthUserManager;
use Drupal\social_auth_instagram\InstagramAuthManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Returns responses for Simple Instagram Connect module routes.
 */
class SegallIoInstagramAuthController extends ControllerBase {

  /**
   * The network plugin manager.
   *
   * @var \Drupal\social_api\Plugin\NetworkManager
   */
  private $networkManager;

  /**
   * The user manager.
   *
   * @var \Drupal\social_auth\SocialAuthUserManager
   */
  private $userManager;

  /**
   * The instagram authentication manager.
   *
   * @var \Drupal\social_auth_instagram\InstagramAuthManager
   */
  private $instagramManager;

  /**
   * Used to access GET parameters.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $request;

  /**
   * The Social Auth Data Handler.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  private $dataHandler;


  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * InstagramAuthController constructor.
   *
   * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
   *   Used to get an instance of social_auth_instagram network plugin.
   * @param \Drupal\social_auth\SocialAuthUserManager $user_manager
   *   Manages user login/registration.
   * @param \Drupal\social_auth_instagram\InstagramAuthManager $instagram_manager
   *   Used to manage authentication methods.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   Used to access GET parameters.
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $social_auth_data_handler
   *   SocialAuthDataHandler object.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Used for logging errors.
   */
  public function __construct(NetworkManager $network_manager, SocialAuthUserManager $user_manager, InstagramAuthManager $instagram_manager, RequestStack $request, SessionInterface $social_auth_data_handler, LoggerChannelFactoryInterface $logger_factory) {

    $this->networkManager = $network_manager;
    $this->userManager = $user_manager;
    $this->instagramManager = $instagram_manager;
    $this->request = $request;
    $this->dataHandler = $social_auth_data_handler;
    $this->loggerFactory = $logger_factory;

    // Sets the plugin id.
    $this->userManager->setPluginId('social_auth_instagram');

    // Sets the session keys to nullify if user could not logged in.
    $this->userManager->setSessionKeysToNullify(['access_token', 'oauth2state']);
    $this->setting = $this->config('social_auth_instagram.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.network.manager'),
      $container->get('social_auth.user_manager'),
      $container->get('social_auth_instagram.manager'),
      $container->get('request_stack'),
      $container->get('session'),
      $container->get('logger.factory')
    );
  }

  /**
   * Response for path 'user/login/instagram'.
   *
   * Redirects the user to Instagram for authentication.
   */
  public function redirectToInstagram() {
    /* @var \League\OAuth2\Client\Provider\Instagram false $instagram */
    $instagram = $this->networkManager->createInstance('segallio_instagram_social_auth_instagram')->getSdk();

    // If instagram client could not be obtained.
    if (!$instagram) {
      drupal_set_message($this->t('Social Auth Instagram not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    // Instagram service was returned, inject it to $instagramManager.
    $this->instagramManager->setClient($instagram);

    // Generates the URL where the user will be redirected for Instagram login.
    // If the user did not have email permission granted on previous attempt,
    // we use the re-request URL requesting only the email address.
    $instagram_login_url = $this->instagramManager->getInstagramLoginUrl();

    $state = $this->instagramManager->getState();

    $this->dataHandler->set('oauth2state', $state);

    return new TrustedRedirectResponse($instagram_login_url);
  }

  /**
   * Response for path 'user/login/instagram/callback'.
   *
   * Instagram returns the user here after user has authenticated in Instagram.
   */
  public function callback() {
    // Checks if user cancel login via Instagram.
    $error = $this->request->getCurrentRequest()->get('error');
    if ($error == 'access_denied') {
      drupal_set_message($this->t('You could not be authenticated.'), 'error');
      return $this->redirect('user.login');
    }

    /* @var \League\OAuth2\Client\Provider\Instagram false $instagram */
    $instagram = $this->networkManager->createInstance('segallio_instagram_social_auth_instagram')->getSdk();

    // If Instagram client could not be obtained.
    if (!$instagram) {
      drupal_set_message($this->t('Social Auth Instagram not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    $state = $this->dataHandler->get('oauth2state');

    // Retreives $_GET['state'].
    $retrievedState = $this->request->getCurrentRequest()->query->get('state');
    if (empty($retrievedState) || ($retrievedState !== $state)) {
      $this->userManager->nullifySessionKeys();
      drupal_set_message($this->t('Instagram login failed. Unvalid oAuth2 State.'), 'error');
      return $this->redirect('user.login');
    }

    // Saves access token to session.
    $this->dataHandler->set('access_token', $this->instagramManager->getAccessToken());

    $this->instagramManager->setClient($instagram)->authenticate();

    // Gets user's info from Instagram API.
    /** InstagramResourceOwner $instagram_profile */
    if (!$instagram_profile = $this->instagramManager->getUserInfo()) {
      drupal_set_message($this->t('Instagram login failed, could not load Instagram profile. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    // Since this project handle only a one-man show, I'm overriding the
    // original callback and set the account access token as the general access
    // token.

    if ($instagram_profile->getNickname() != 'roysegall') {
      drupal_set_message($this->t('Only RoySegall is allowed'), 'error');
      return $this->redirect('user.login');
    }

    $this->dataHandler->set('instagram_access_token', $this->instagramManager->getAccessToken());

    $account = $this->userManager->loadUserByProperty('uid', 1);
    user_login_finalize($account);
    return $this->redirect('<front>');
  }

}
