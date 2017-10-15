<?php

namespace Drupal\segallio_puller\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\segallio_core\SocialAssetsServicesManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Puller plugins.
 */
abstract class PullerBase extends PluginBase implements PullerInterface {

  use PullerTrait;

  /**
   * The entity storgate.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * The entity query.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface
   */
  protected $entityQuery;

  /**
   * The social service handler.
   *
   * @var \Drupal\segallio_facebook\SegallIOFacebookGraph|\Drupal\segallio_github\SegallIoGithubApiInterface|\Drupal\segallio_instagram\SegallIoInstagramPostsInterface|\Drupal\segallio_twitter\SegallIoTwitterGraphInterface
   */
  protected $social;

  /**
   * PullerBase constructor.
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param EntityTypeManager $entity_type_manager
   * @param SocialAssetsServicesManager $social
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManager $entity_type_manager, SocialAssetsServicesManager $social) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityStorage = $entity_type_manager->getStorage($this->pluginDefinition['entity_type']);
    $this->entityQuery = $this->entityStorage->getQuery();
    $this->social = $social->servicesRouter($this->pluginDefinition['social']);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('social_assets_services_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function update($asset, $entity_id) {
    /** @var ContentEntityInterface $entity */
    $entity = $this->entityStorage->load($entity_id);

    foreach ($asset as $field => $value) {
      $entity->set($field, $value);
    }

    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function insert($asset) {
    $this->entityStorage->create($asset)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function actionRouter($asset) {
    $results = $this->entityStorage->getQuery()->condition('url', $asset['url'])->execute();

    if ($results) {
      return reset($results);
    }
    else {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function processFields($asset) {
    $processed = [];

    foreach ($this->pluginDefinition['fields'] as $field => $mapper) {

      $property_name = is_array($mapper) ? $mapper['field'] : $mapper;
      $default_value = empty($mapper['default_value']) ? "" : $mapper['default_value'];

      if (!empty($asset[$field])) {

        if (is_array($mapper)) {
          $processed[$property_name] = $this->{$mapper['callback']}($asset[$field]);
        }
        else {
          $processed[$property_name] = $asset[$field];
        }
      }
      else {
        $processed[$property_name] = $default_value;
      }
    }

    return $processed;
  }

  /**
   * {@inheritdoc}
   */
  public function pull() {
    $assets = $this->assets();

    // Iterate over the posts.
    foreach ($assets as $i => $asset) {

      // check if the assert already been ported.
      $processed_asset = $this->processFields($asset);

      if ($id = $this->actionRouter($processed_asset)) {
        $this->update($processed_asset, $id);
      }
      else {
        $this->insert($processed_asset);
      }
    }
  }

}
