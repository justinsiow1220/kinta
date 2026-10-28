<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Local configuration for KINTA's short-lived offline access grants.
 */
class KintaAuth extends BaseConfig
{
    public string $offlinePrivateKeyPath = WRITEPATH . 'keys/kinta_offline_grant_private.pem';

    public string $grantIssuer = 'kinta-ci4';

    public string $grantAudience = 'kinta-flutter';

    public string $grantKeyId = 'kinta-local-rsa-1';

    /** Eight hours is a configurable development default; confirm for production. */
    public int $grantLifetimeSeconds = 28800;
}
