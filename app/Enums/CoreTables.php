<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

use Modules\Core\Enums\Concerns\HasModuleTablesUtils;

enum CoreTables: string
{
    use HasModuleTablesUtils;

    // core models
    case Entities = 'core_entities';
    case Taxonomies = 'core_taxonomies';
    case Places = 'core_places';
    case Licenses = 'core_licenses';
    case CronJobs = 'core_cron_jobs';
    case Templates = 'core_templates';
    case Presets = 'core_presets';
    case Fields = 'core_fields';
    case Acls = 'core_acls';
    case UsersGridConfigs = 'core_users_grid_configs';
    case Settings = 'core_settings';
    case ModelEmbeddings = 'core_model_embeddings';
    case RecordOrigins = 'core_record_origins';
    case OutboxEvents = 'core_outbox_events';

    // generic or vendors models
    case Roles = 'vend_roles';
    case Permissions = 'vend_permissions';
    case Versions = 'vend_versions';
    case Modifications = 'vend_modifications';
    case Approvals = 'vend_approvals';
    case Disapprovals = 'vend_disapprovals';

    // do not modify because they are used in the Laravel core environment
    case Users = 'users';
    case PasswordResetTokens = 'password_reset_tokens';
    case Sessions = 'sessions';

    // translations
    case TaxonomiesTranslations = 'core_taxonomies_translations';

    // pivots
    case Presettables = 'core_presettables';
    case Fieldables = 'core_fieldables';
    case ModelHasRoles = 'vend_model_has_roles';
    case ModelHasPermissions = 'vend_model_has_permissions';
    case RoleHasPermissions = 'vend_role_has_permissions';
}
