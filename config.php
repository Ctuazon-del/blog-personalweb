<?php

function brightpath_owner_passcode(): string {
  $localConfigFile = __DIR__ . '/config.local.php';

  if (file_exists($localConfigFile)) {
    $localConfig = require $localConfigFile;
    if (is_array($localConfig) && !empty($localConfig['owner_passcode'])) {
      return (string) $localConfig['owner_passcode'];
    }
  }

  $envPasscode = getenv('BRIGHTPATH_OWNER_PASSCODE');
  return $envPasscode ? (string) $envPasscode : '';
}

function brightpath_passcode_is_valid($submittedPasscode): bool {
  $ownerPasscode = brightpath_owner_passcode();
  return $ownerPasscode !== '' && hash_equals($ownerPasscode, (string) $submittedPasscode);
}
