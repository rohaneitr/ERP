<?php
namespace App\Utils;

use Illuminate\Support\Facades\Cache;

class LicenseManager
{
    /**
     * The Public Key used to verify the license signature.
     * (We will generate the real one later, this is a placeholder)
     */
    private $publicKey = <<<EOD
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA3trXzf9F4u2RnRZBAHc6
4Y6mQyP1O2IKzHMblRzK3t3t+36XV0GbzEJZbfP5RN+TxTGFgxy2que4JcTlJ4yL
SBtEc6g4WbHOYoEp9iXmMMDk3f0cy8/bbRWHKhGHGiD84FzS1MNCH1e6mGnKJVjI
XBXknRlo39iS2+aIqwne3mtLSfkhptz+gf7ygeHIN1ntiDIDLU+0VXkRYMcMTXRj
XjfRdnbyC8zW0IMMXDCZtfu3A+TStWV1u2UF71CdrbSIk+bj82xHUbiSkkrdSBTh
cDdE0JUiVjYNhPEiKDEfoOiJtCwz/b+tgB671pfvoEqYVcBfxhXHyy7aujhNOYtm
bQIDAQAB
-----END PUBLIC KEY-----
EOD;

    /**
     * Get the unique Hardware ID of the Windows machine
     */
    public static function getHardwareId()
    {
        // Execute Windows Management Instrumentation command to get Motherboard UUID
        $output = [];
        exec('wmic csproduct get uuid', $output);
        if (isset($output[1]) && trim($output[1]) !== '') {
            return md5(trim($output[1]));
        }
        
        // Fallback to Disk Drive Serial
        $output = [];
        exec('wmic diskdrive get serialnumber', $output);
        if (isset($output[1]) && trim($output[1]) !== '') {
            return md5(trim($output[1]));
        }

        return md5('UNKNOWN_HARDWARE_ID');
    }

    /**
     * Validate the current license key
     */
    public function validateLicense($licenseKey)
    {
        try {
            // 1. Decode the base64 license key
            $decoded = base64_decode($licenseKey);
            $parts = explode('|', $decoded);
            
            if (count($parts) !== 3) {
                return ['valid' => false, 'message' => 'Invalid License Format.'];
            }

            $hwid = $parts[0];
            $expiryDate = $parts[1]; // Format: YYYY-MM-DD
            $signature = base64_decode($parts[2]);

            // 2. Verify Hardware ID
            if ($hwid !== self::getHardwareId()) {
                return ['valid' => false, 'message' => 'Hardware mismatch. This license is bound to another computer.'];
            }

            // 3. Verify Signature
            $dataToVerify = $hwid . '|' . $expiryDate;
            $isValidSignature = openssl_verify($dataToVerify, $signature, $this->publicKey, OPENSSL_ALGO_SHA256);
            
            if ($isValidSignature !== 1) {
                return ['valid' => false, 'message' => 'License signature is corrupted or forged.'];
            }

            // 4. Verify Time (Anti-Time Travel)
            $currentTime = $this->getSecureNetworkTime();
            if (strtotime($currentTime) > strtotime($expiryDate . ' 23:59:59')) {
                return ['valid' => false, 'message' => 'License has expired. Please renew.'];
            }

            return ['valid' => true, 'expiry' => $expiryDate];

        } catch (\Exception $e) {
            return ['valid' => false, 'message' => 'License verification failed.'];
        }
    }

    /**
     * Get time from a secure online source to prevent local clock tampering
     */
    private function getSecureNetworkTime()
    {
        // Cache the network time for 1 hour to prevent API rate limiting
        return Cache::remember('secure_network_time', 3600, function () {
            try {
                // Example using WorldTimeAPI (or standard PHP time if offline)
                $context = stream_context_create(['http' => ['timeout' => 3]]);
                $json = file_get_contents('http://worldtimeapi.org/api/timezone/Etc/UTC', false, $context);
                $data = json_decode($json);
                return date('Y-m-d H:i:s', strtotime($data->datetime));
            } catch (\Exception $e) {
                // Fallback to local time if strictly offline, though this is a vulnerability vector
                // In a strict mode, you might return an error here preventing offline usage
                return date('Y-m-d H:i:s');
            }
        });
    }
}
