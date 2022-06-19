<?php

namespace Drupal\farm_migrate\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigrateRowDeleteEvent;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Farm migration event subscriber.
 */
class FarmMigrationSubscriber implements EventSubscriberInterface {

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The state key/value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Migration plugin manager service.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * FarmMigrationSubscriber Constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key/value store.
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager
   *   Migration plugin manager service.
   */
  public function __construct(Connection $database, TimeInterface $time, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, StateInterface $state, MigrationPluginManagerInterface $migration_plugin_manager) {
    $this->database = $database;
    $this->time = $time;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->state = $state;
    $this->migrationPluginManager = $migration_plugin_manager;
  }

  /**
   * Get subscribed events.
   *
   * @inheritdoc
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::PRE_IMPORT][] = ['onMigratePreImport'];
    $events[MigrateEvents::POST_IMPORT][] = ['onMigratePostImport'];
    $events[MigrateEvents::POST_ROW_SAVE][] = ['onMigratePostRowSave'];
    $events[MigrateEvents::PRE_ROW_DELETE][] = ['onMigratePreRowDelete'];
    return $events;
  }

  /**
   * Run pre-migration logic.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event object.
   */
  public function onMigratePreImport(MigrateImportEvent $event) {
    $this->unblockUsers($event);
    $this->grantTextFormatPermission($event);
    $this->allowPrivateFileReferencing($event);
  }

  /**
   * Run post-migration logic.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event object.
   */
  public function onMigratePostImport(MigrateImportEvent $event) {
    $this->blockUsers($event);
    $this->revokeTextFormatPermission($event);
    $this->preventPrivateFileReferencing($event);
    $this->addRevisionLogMessage($event);
  }

  /**
   * Run row post-save logic.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The row save event object.
   */
  public function onMigratePostRowSave(MigratePostRowSaveEvent $event) {
    $this->migrateSensorListenerData($event);
  }

  /**
   * Run row pre-deletion logic.
   *
   * @param \Drupal\migrate\Event\MigrateRowDeleteEvent $event
   *   The row delete event object.
   */
  public function onMigratePreRowDelete(MigrateRowDeleteEvent $event) {
    $this->deleteAssetParentReferences($event);
    $this->deleteLogQuantityReferences($event);
    $this->deleteTermParentReferences($event);
    $this->deletePlantTypeCompanionReferences($event);
    $this->deleteSensorDataStreamReferences($event);
  }

  /**
   * Unblock users that were blocked in the 1.x instance.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event object.
   */
  public function unblockUsers(MigrateImportEvent $event) {

    // When records are migrated, Drupal will switch to the user that created
    // them in order to perform validation. If that user was blocked, then it
    // the record will not validate, and will not be migrated. To work around
    // this we will temporarily unblock all users, and then block them again
    // after the migration finishes. We use Drupal's state system to remember
    // which users need to be blocked.
    // @see blockUsers()
    $migration = $event->getMigration();
    if ($migration->migration_group == 'farm_migrate_user') {
      return;
    }
    $user_settings = $this->configFactory->getEditable('user.settings');
    $status_activated = $user_settings->get('notify.status_activated');
    $user_settings->set('notify.status_activated', FALSE)->save();
    $storage = $this->entityTypeManager->getStorage('user');
    $user_ids = $storage->getQuery()->condition('status', FALSE)->execute();
    $this->state->set('farm_migrate_blocked_users', $user_ids);
    foreach ($user_ids as $id) {
      if (!empty($id)) {
        $user = $storage->load($id);
        $user->activate();
        $user->save();
      }
    }
    $user_settings->set('notify.status_activated', $status_activated)->save();
  }

  /**
   * Block users that were unblocked via unblockUsers().
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event object.
   */
  public function blockUsers(MigrateImportEvent $event) {

    // Block users that were temporarily unblocked.
    // @see unblockUsers()
    $migration = $event->getMigration();
    if ($migration->migration_group == 'farm_migrate_user') {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('user');
    $user_ids = $this->state->get('farm_migrate_blocked_users', []);
    foreach ($user_ids as $id) {
      if (!empty($id)) {
        $user = $storage->load($id);
        $user->block();
        $user->save();
      }
    }
    $this->state->delete('farm_migrate_blocked_users');
  }

  /**
   * Grant default text format permission to anonymous role.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event object.
   */
  public function grantTextFormatPermission(MigrateImportEvent $event) {

    // If the migration is in the farm_migrate_taxonomy migration group,
    // grant the 'use text format default' permission to anonymous role.
    // This allows entity validation to pass even when the migration is run
    // via Drush (which runs as the anonymous user). The permission is revoked
    // in post-migration.
    // @see revokeTextFormatPermission()
    $migration = $event->getMigration();
    if (isset($migration->migration_group) && $migration->migration_group == 'farm_migrate_taxonomy') {
      $storage = $this->entityTypeManager->getStorage('user_role');
      $anonymous = $storage->load('anonymous');
      $anonymous->grantPermission('use text format default');
      $anonymous->save();
    }
  }

  /**
   * Revoke default text format permission from anonymous role.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event object.
   */
  public function revokeTextFormatPermission(MigrateImportEvent $event) {

    // If the migration is in the farm_migrate_taxonomy migration group,
    // revoke the 'use text format default' permission to anonymous role.
    // This permission was added in pre-migration.
    // @see grantTextFormatPermission()
    $migration = $event->getMigration();
    if (isset($migration->migration_group) && $migration->migration_group == 'farm_migrate_taxonomy') {
      $storage = $this->entityTypeManager->getStorage('user_role');
      $anonymous = $storage->load('anonymous');
      $anonymous->revokePermission('use text format default');
      $anonymous->save();
    }
  }

  /**
   * Temporarily allow private files to be referenced by entities.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event object.
   */
  public function allowPrivateFileReferencing(MigrateImportEvent $event) {

    // During shambaOS 1.x -> 2.x migrations, Drupal's FileAccessControlHandler
    // will not allow file entities to be referenced unless they were originally
    // uploaded by the same user that created the entity that references them.
    // In shambaOS, it is common for an entity to be created by one user, and
    // photos to be uploaded to it later by a different user. With entity
    // validation enabled on the migration, this throws a validation error and
    // doesn't allow the file to be referenced.
    // We work around this by setting a Drupal state variable during our
    // migrations, and check for it in hook_ENTITY_TYPE_access(), so we can
    // explicitly grant access to the files.
    // This state is removed post-migration.
    // @see \Drupal\file\FileAccessControlHandler
    // @see farm_migrate_file_access()
    // @see preventPrivateFileReferencing()
    $migration_groups = [
      'farm_migrate_area',
      'farm_migrate_asset',
      'farm_migrate_log',
      'farm_migrate_plan',
      'farm_migrate_taxonomy',
    ];
    $migration = $event->getMigration();
    if (isset($migration->migration_group) && in_array($migration->migration_group, $migration_groups)) {
      $this->state->set('farm_migrate_allow_file_referencing', TRUE);
    }
  }

  /**
   * Prevent private files from being referenced by entities.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event object.
   */
  public function preventPrivateFileReferencing(MigrateImportEvent $event) {

    // Unset the Drupal state variable that was set to temporarily allow private
    // files to be referenced by entities.
    // @see farm_migrate_file_access()
    // @see allowPrivateFileReferencing()
    $this->state->delete('farm_migrate_allow_file_referencing');
  }

  /**
   * Add a revision log message to imported entities.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event object.
   */
  public function addRevisionLogMessage(MigrateImportEvent $event) {

    // Define the migration groups that we will post-process and their
    // corresponding entity revision tables.
    $groups_revision_tables = [
      'farm_migrate_asset' => 'asset_revision',
      'farm_migrate_area' => 'asset_revision',
      'farm_migrate_log' => 'log_revision',
      'farm_migrate_plan' => 'plan_revision',
      'farm_migrate_quantity' => 'quantity_revision',
      'farm_migrate_taxonomy' => 'taxonomy_term_revision',
    ];
    $migration = $event->getMigration();
    if (isset($migration->migration_group) && array_key_exists($migration->migration_group, $groups_revision_tables)) {

      // Define the entity id column name. This will be "id" in all cases
      // except taxonomy_terms, which use "tid".
      $id_column = 'id';
      if ($migration->migration_group == 'farm_migrate_taxonomy') {
        $id_column = 'tid';
      }

      // Build a query to set the revision log message.
      $revision_table = $groups_revision_tables[$migration->migration_group];
      $migration_id = $migration->id();
      $query = "UPDATE {$revision_table}
        SET revision_log_message = :revision_log_message
        WHERE revision_id IN (
          SELECT r.revision_id
          FROM {migrate_map_$migration_id} mm
          INNER JOIN {$revision_table} r ON mm.destid1 = r.$id_column
        )";
      $args = [
        ':revision_log_message' => 'Migrated from shambaOS 1.x on ' . date('Y-m-d', $this->time->getRequestTime()),
      ];
      $this->database->query($query, $args);
    }
  }

  /**
   * Migrate sensor listener data.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The post row save migrate event.
   */
  public function migrateSensorListenerData(MigratePostRowSaveEvent $event) {

    $migration = $event->getMigration();
    $migration_id = $migration->id();

    // Migrate listener sensor data for each data stream.
    if ($migration_id === "farm_migrate_sensor_listener_data_streams") {

      // Get values to identify the source data.
      $migration_row = $event->getRow();
      $source_id = $migration_row->getSourceProperty('id');
      $source_name = $migration_row->getSourceProperty('name');

      // Get the destination data stream ID.
      $destination_id = $event->getDestinationIdValues()[0];

      // Query the source for data. Override the ID field that is returned with
      // each row to be the ID of the migrated data stream.
      $query = "SELECT :id as id, timestamp, value_numerator, value_denominator
          FROM {farm_sensor_data} fsd
          WHERE fsd.id = :sensor_id and fsd.name = :name
         ";
      $args = [
        'id' => $destination_id,
        'sensor_id' => $source_id,
        'name' => $source_name,
      ];
      $source_db = Database::getConnection('default', 'migrate');
      $source_data = $source_db->query($query, $args);
      $source_data->setFetchMode(\PDO::FETCH_ASSOC);

      // Start an insert statement.
      $insert = $this->database->insert('data_stream_basic')
        ->fields(['id', 'timestamp', 'value_numerator', 'value_denominator']);

      // Loop through the source data and insert in batches.
      $batch_size = 10000;
      $count = 0;
      foreach ($source_data as $data) {
        $insert->values($data);
        $count++;
        if ($count >= $batch_size) {
          $insert->execute();
          $count = 0;
          $insert = $this->database->insert('data_stream_basic')
            ->fields(['id', 'timestamp', 'value_numerator', 'value_denominator']);
        }
      }
      $insert->execute();
    }
  }

  /**
   * Delete references to quantities from logs.
   *
   * @param \Drupal\migrate\Event\MigrateRowDeleteEvent $event
   *   The row delete event object.
   */
  public function deleteLogQuantityReferences(MigrateRowDeleteEvent $event) {

    // If the migration is in the farm_migrate_log migration group, delete all
    // references to quantity entities from the log that is being deleted.
    // This prevents the quantity entity itself from being deleted by
    // LogEventSubscriber in the farm_log_quantity module.
    // We limit this to quantities that were created via migrations in the
    // farm_migrate_quantity migration group to ensure that quantities created
    // via the create_quantity process plugin can be deleted normally with logs.
    // @see \Drupal\farm_log_quantity\EventSubscriber\LogEventSubscriber
    $migration = $event->getMigration();
    if (isset($migration->migration_group) && $migration->migration_group == 'farm_migrate_log') {
      $id_values = $event->getDestinationIdValues();
      if (!empty($id_values['id'])) {
        $migration_plugins = $this->migrationPluginManager->createInstances([]);
        foreach ($migration_plugins as $migration_id => $migration_plugin) {
          if (isset($migration_plugin->migration_group) && $migration_plugin->migration_group == 'farm_migrate_quantity') {
            $table = 'migrate_map_' . $migration_id;
            $this->database->query('DELETE FROM {log__quantity} WHERE entity_id = :id AND quantity_target_id IN (SELECT destid1 FROM ' . $table . ')', [':id' => $id_values['id']]);
            $this->database->query('DELETE FROM {log_revision__quantity} WHERE entity_id = :id AND quantity_target_id IN (SELECT destid1 FROM ' . $table . ')', [':id' => $id_values['id']]);
          }
        }
      }
    }
  }

  /**
   * Delete parent references from assets.
   *
   * @param \Drupal\migrate\Event\MigrateRowDeleteEvent $event
   *   The row delete event object.
   */
  public function deleteAssetParentReferences(MigrateRowDeleteEvent $event) {

    // If the migration is in the farm_migrate_asset or farm_migrate_area
    // migration groups, delete all parent references to the destination asset.
    // This is necessary because the field is populated by migrations in the
    // farm_migrate_asset_parent group, which ONLY set the parent field on
    // existing assets, and rolling back those migrations does not remove the
    // parent references. This causes entity reference integrity constraint
    // errors if an attempt is made to roll back assets that are referenced as
    // parents.
    $migration = $event->getMigration();
    if (isset($migration->migration_group) && in_array($migration->migration_group, ['farm_migrate_asset', 'farm_migrate_area'])) {
      $id_values = $event->getDestinationIdValues();
      if (!empty($id_values['id'])) {
        $this->database->query('DELETE FROM {asset__parent} WHERE parent_target_id = :id', [':id' => $id_values['id']]);
        $this->database->query('DELETE FROM {asset_revision__parent} WHERE parent_target_id = :id', [':id' => $id_values['id']]);
      }
    }
  }

  /**
   * Delete parent references to taxonomy pterms.
   *
   * @param \Drupal\migrate\Event\MigrateRowDeleteEvent $event
   *   The row delete event object.
   */
  public function deleteTermParentReferences(MigrateRowDeleteEvent $event) {

    // If the migration is in the farm_migrate_taxonomy migration group, delete
    // all parent references to the destination term.
    // This is necessary to prevent entity reference integrity constraint errors
    // if an attempt is made to roll back terms that are referenced as parents.
    $migration = $event->getMigration();
    if (isset($migration->migration_group) && $migration->migration_group == 'farm_migrate_taxonomy') {
      $id_values = $event->getDestinationIdValues();
      if (!empty($id_values['tid'])) {
        $this->database->query('DELETE FROM {taxonomy_term__parent} WHERE parent_target_id = :tid', [':tid' => $id_values['tid']]);
        $this->database->query('DELETE FROM {taxonomy_term_revision__parent} WHERE parent_target_id = :tid', [':tid' => $id_values['tid']]);
      }
    }
  }

  /**
   * Delete companion references to plant type terms.
   *
   * @param \Drupal\migrate\Event\MigrateRowDeleteEvent $event
   *   The row delete event object.
   */
  public function deletePlantTypeCompanionReferences(MigrateRowDeleteEvent $event) {

    // If this is the farm_migrate_taxonomy_plant_type migration, delete all
    // companion references to the destination term.
    // This is necessary to prevent entity reference integrity constraint errors
    // if an attempt is made to roll back terms that are referenced as
    // companions.
    $migration = $event->getMigration();
    if ($migration->id() == 'farm_migrate_taxonomy_plant_type') {
      $id_values = $event->getDestinationIdValues();
      if (!empty($id_values['tid'])) {
        $this->database->query('DELETE FROM {taxonomy_term__companions} WHERE companions_target_id = :tid', [':tid' => $id_values['tid']]);
        $this->database->query('DELETE FROM {taxonomy_term_revision__companions} WHERE companions_target_id = :tid', [':tid' => $id_values['tid']]);
      }
    }
  }

  /**
   * Delete data stream references from sensor assets.
   *
   * @param \Drupal\migrate\Event\MigrateRowDeleteEvent $event
   *   The row delete event object.
   */
  public function deleteSensorDataStreamReferences(MigrateRowDeleteEvent $event) {

    // If this is the farm_migrate_sensor_listener_data_streams migration,
    // delete all references to the destination data_stream from sensor assets.
    // This is necessary to prevent entity reference integrity constraint errors
    // if an attempt is made to roll back data_streams that are referenced by
    // sensor assets. During migration, these references are created by the
    // DataStream migrate destination plugin, so they will be recreated if this
    // migration is imported again after rollback.
    // @see \Drupal\data_stream\Plugin\migrate\destination\DataStream
    $migration = $event->getMigration();
    if ($migration->id() == 'farm_migrate_sensor_listener_data_streams') {
      $id_values = $event->getDestinationIdValues();
      if (!empty($id_values['id'])) {
        $this->database->query('DELETE FROM {asset__data_stream} WHERE data_stream_target_id = :id', [':id' => $id_values['id']]);
        $this->database->query('DELETE FROM {asset_revision__data_stream} WHERE data_stream_target_id = :id', [':id' => $id_values['id']]);
      }
    }
  }

}
