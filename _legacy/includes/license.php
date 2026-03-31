<?php
/**
 * ============================================================
 *  Ultimate Mods – FiveM Community Manager
 * ============================================================
 *  License Validation System (Simple API)
 * ============================================================
 */

// Prevent multiple includes
if (defined('UM_LICENSE_LOADED')) return;
define('UM_LICENSE_LOADED', true);

// License API Configuration
define('UM_LICENSE_API_URL', 'https://ultimate-mods.com/api/nexus/lkey/');
define('UM_LICENSE_API_KEY', 'a45668e5a4de9a9ca4a4bc9dc6bca873');

// License error types
define('UM_LICENSE_ERROR_NONE', 0);
define('UM_LICENSE_ERROR_API_UNREACHABLE', 1);
define('UM_LICENSE_ERROR_INVALID_KEY', 2);
define('UM_LICENSE_ERROR_EXPIRED', 3);
define('UM_LICENSE_ERROR_CANCELED', 4);
define('UM_LICENSE_ERROR_INACTIVE', 5);
define('UM_LICENSE_ERROR_DOMAIN_MISMATCH', 6);
define('UM_LICENSE_ERROR_NO_KEY', 7);

/**
 * Get the current site's domain
 */
function getCurrentSiteDomain() {
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $host = preg_replace('/:\d+$/', '', $host);
    $host = preg_replace('/^www\./', '', strtolower(trim($host)));
    return $host;
}

/**
 * Extract domain from URL
 */
function extractDomainFromUrl($url) {
    if (empty($url)) return '';
    $url = trim($url);
    $url = str_replace('\/', '/', $url);
    
    // If it doesn't have a scheme, add one for parse_url to work
    if (!preg_match('/^https?:\/\//i', $url)) {
        // Check if it looks like a domain (not a path)
        if (preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z]{2,})+/', $url)) {
            $url = 'https://' . $url;
        }
    }
    
    $parsed = parse_url($url);
    $host = $parsed['host'] ?? $url; // Fallback to original if parse fails
    $host = preg_replace('/^www\./', '', strtolower(trim($host)));
    
    // Remove any port number
    $host = preg_replace('/:\d+$/', '', $host);
    
    return $host;
}

/**
 * Extract domain from stored validation response JSON
 */
function extractDomainFromStoredResponse($validationResponse) {
    if (empty($validationResponse)) return null;
    
    $data = json_decode($validationResponse, true);
    if (!is_array($data)) return null;
    
    // Use the same extraction logic as in validateLicenseKey
    $licensedDomain = null;
    
    if (isset($data['customFields']) && is_array($data['customFields'])) {
        $possibleKeys = ['1', 1, 'domain', 'url', 'website', 'Domain', 'URL', 'Website', '0', 0];
        foreach ($possibleKeys as $key) {
            if (isset($data['customFields'][$key]) && !empty($data['customFields'][$key])) {
                $fieldValue = $data['customFields'][$key];
                if (filter_var($fieldValue, FILTER_VALIDATE_URL) || 
                    preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z]{2,})+$/', $fieldValue)) {
                    $licensedDomain = extractDomainFromUrl($fieldValue);
                    break;
                }
            }
        }
        
        if (empty($licensedDomain)) {
            foreach ($data['customFields'] as $key => $value) {
                if (!empty($value) && is_string($value)) {
                    if (filter_var($value, FILTER_VALIDATE_URL) || 
                        preg_match('/^(https?:\/\/)?[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z]{2,})+/', $value)) {
                        $licensedDomain = extractDomainFromUrl($value);
                        break;
                    }
                }
            }
        }
    }
    
    if (empty($licensedDomain)) {
        $topLevelFields = ['domain', 'url', 'website', 'licensed_domain', 'licensedDomain'];
        foreach ($topLevelFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $licensedDomain = extractDomainFromUrl($data[$field]);
                break;
            }
        }
    }
    
    return $licensedDomain;
}

/**
 * Validate license key via API
 */
function validateLicenseKey($licenseKey) {
    $licenseKey = trim($licenseKey);
    
    if (empty($licenseKey)) {
        return ['valid' => false, 'error' => 'License key is required.', 'data' => null, 'error_type' => UM_LICENSE_ERROR_NO_KEY];
    }
    
    $url = UM_LICENSE_API_URL . urlencode($licenseKey);
    
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => UM_LICENSE_API_KEY . ":",
        CURLOPT_USERAGENT => "UltimateMods-CommunityManager/1.3",
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    
    if ($response === false || !empty($curlError)) {
        return [
            'valid' => false,
            'error' => 'Unable to connect to license server. Please check your internet connection.',
            'data' => null,
            'error_type' => UM_LICENSE_ERROR_API_UNREACHABLE
        ];
    }
    
    if ($httpCode === 404) {
        return [
            'valid' => false,
            'error' => 'Invalid license key. Please check your key and try again.',
            'data' => null,
            'error_type' => UM_LICENSE_ERROR_INVALID_KEY
        ];
    }
    
    if ($httpCode !== 200) {
        return [
            'valid' => false,
            'error' => "License server error (HTTP $httpCode). Please try again.",
            'data' => null,
            'error_type' => UM_LICENSE_ERROR_API_UNREACHABLE
        ];
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        return [
            'valid' => false,
            'error' => 'Invalid response from license server.',
            'data' => null,
            'error_type' => UM_LICENSE_ERROR_API_UNREACHABLE
        ];
    }
    
    // Check if canceled
    if (isset($data['canceled']) && $data['canceled'] === true) {
        return [
            'valid' => false,
            'error' => 'This license has been canceled.',
            'data' => $data,
            'error_type' => UM_LICENSE_ERROR_CANCELED
        ];
    }
    
    // Check if active
    if (isset($data['active']) && $data['active'] !== true) {
        return [
            'valid' => false,
            'error' => 'This license is not active.',
            'data' => $data,
            'error_type' => UM_LICENSE_ERROR_INACTIVE
        ];
    }
    
    // Check expiration
    if (!empty($data['expires'])) {
        $expiresAt = strtotime($data['expires']);
        if ($expiresAt && $expiresAt < time()) {
            return [
                'valid' => false,
                'error' => 'This license has expired on ' . date('F j, Y', $expiresAt) . '.',
                'data' => $data,
                'error_type' => UM_LICENSE_ERROR_EXPIRED
            ];
        }
    }
    
    // Check domain - extract from customFields and compare to current domain
    $licensedDomain = null;
    if (isset($data['customFields']) && is_array($data['customFields'])) {
        // Try multiple possible field keys for domain/URL
        $possibleKeys = ['1', 1, 'domain', 'url', 'website', 'Domain', 'URL', 'Website', '0', 0];
        foreach ($possibleKeys as $key) {
            if (isset($data['customFields'][$key]) && !empty($data['customFields'][$key])) {
                $fieldValue = $data['customFields'][$key];
                // Check if it looks like a URL or domain
                if (filter_var($fieldValue, FILTER_VALIDATE_URL) || 
                    preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z]{2,})+$/', $fieldValue)) {
                    $licensedDomain = extractDomainFromUrl($fieldValue);
                    break;
                }
            }
        }
        
        // If still not found, iterate through all customFields looking for URL-like values
        if (empty($licensedDomain)) {
            foreach ($data['customFields'] as $key => $value) {
                if (!empty($value) && is_string($value)) {
                    if (filter_var($value, FILTER_VALIDATE_URL) || 
                        preg_match('/^(https?:\/\/)?[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z]{2,})+/', $value)) {
                        $licensedDomain = extractDomainFromUrl($value);
                        break;
                    }
                }
            }
        }
    }
    
    // Also check top-level fields that might contain domain info
    if (empty($licensedDomain)) {
        $topLevelFields = ['domain', 'url', 'website', 'licensed_domain', 'licensedDomain'];
        foreach ($topLevelFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $licensedDomain = extractDomainFromUrl($data[$field]);
                break;
            }
        }
    }
    
    if (!empty($licensedDomain)) {
        $currentDomain = getCurrentSiteDomain();
        
        // Compare domains (case-insensitive, without www)
        if (strtolower($licensedDomain) !== strtolower($currentDomain)) {
            return [
                'valid' => false,
                'error' => "This license is registered for '$licensedDomain' but you're using '$currentDomain'. Please use the correct domain or contact support to transfer your license.",
                'data' => $data,
                'error_type' => UM_LICENSE_ERROR_DOMAIN_MISMATCH,
                'licensed_domain' => $licensedDomain,
                'current_domain' => $currentDomain
            ];
        }
    }
    
    return ['valid' => true, 'error' => null, 'data' => $data, 'error_type' => UM_LICENSE_ERROR_NONE];
}

/**
 * Store license information in the database
 */
function storeLicenseInfo($conn, $licenseKey, $apiData) {
    $customerName = $apiData['customer']['name'] ?? null;
    $customerEmail = $apiData['customer']['email'] ?? null;
    $customerId = isset($apiData['customer']['id']) ? intval($apiData['customer']['id']) : null;
    $purchasedAt = !empty($apiData['purchased']) ? date('Y-m-d H:i:s', strtotime($apiData['purchased'])) : null;
    $expiresAt = !empty($apiData['expires']) ? date('Y-m-d H:i:s', strtotime($apiData['expires'])) : null;
    $isActive = ($apiData['active'] ?? false) ? 1 : 0;
    $isCanceled = ($apiData['canceled'] ?? false) ? 1 : 0;
    $licenseId = isset($apiData['id']) ? intval($apiData['id']) : null;
    $productName = $apiData['name'] ?? 'FiveM Community Manager';
    $responseJson = json_encode($apiData);
    
    // Extract licensed domain from customFields (matching validateLicenseKey logic)
    $licensedDomain = null;
    if (isset($apiData['customFields']) && is_array($apiData['customFields'])) {
        // Try multiple possible field keys for domain/URL
        $possibleKeys = ['1', 1, 'domain', 'url', 'website', 'Domain', 'URL', 'Website', '0', 0];
        foreach ($possibleKeys as $key) {
            if (isset($apiData['customFields'][$key]) && !empty($apiData['customFields'][$key])) {
                $fieldValue = $apiData['customFields'][$key];
                // Check if it looks like a URL or domain
                if (filter_var($fieldValue, FILTER_VALIDATE_URL) || 
                    preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z]{2,})+$/', $fieldValue)) {
                    $licensedDomain = extractDomainFromUrl($fieldValue);
                    break;
                }
            }
        }
        
        // If still not found, iterate through all customFields looking for URL-like values
        if (empty($licensedDomain)) {
            foreach ($apiData['customFields'] as $key => $value) {
                if (!empty($value) && is_string($value)) {
                    if (filter_var($value, FILTER_VALIDATE_URL) || 
                        preg_match('/^(https?:\/\/)?[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z]{2,})+/', $value)) {
                        $licensedDomain = extractDomainFromUrl($value);
                        break;
                    }
                }
            }
        }
    }
    
    // Also check top-level fields that might contain domain info
    if (empty($licensedDomain)) {
        $topLevelFields = ['domain', 'url', 'website', 'licensed_domain', 'licensedDomain'];
        foreach ($topLevelFields as $field) {
            if (isset($apiData[$field]) && !empty($apiData[$field])) {
                $licensedDomain = extractDomainFromUrl($apiData[$field]);
                break;
            }
        }
    }
    
    // Check if license exists
    $stmt = $conn->prepare("SELECT id FROM license_info LIMIT 1");
    if (!$stmt) return false;
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($existing) {
        $sql = "UPDATE license_info SET 
            license_key = ?, license_id = ?, product_name = ?,
            customer_name = ?, customer_email = ?, customer_id = ?,
            licensed_domain = ?, purchased_at = ?, expires_at = ?, 
            is_active = ?, is_canceled = ?,
            last_validated_at = NOW(), validation_response = ?
            WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param("sisssisssiisi", 
            $licenseKey, $licenseId, $productName,
            $customerName, $customerEmail, $customerId,
            $licensedDomain, $purchasedAt, $expiresAt,
            $isActive, $isCanceled, $responseJson, $existing['id']);
    } else {
        $sql = "INSERT INTO license_info 
            (license_key, license_id, product_name, customer_name, customer_email, customer_id, 
             licensed_domain, purchased_at, expires_at, is_active, is_canceled, 
             last_validated_at, validation_response) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param("sisssisssiis", 
            $licenseKey, $licenseId, $productName,
            $customerName, $customerEmail, $customerId,
            $licensedDomain, $purchasedAt, $expiresAt,
            $isActive, $isCanceled, $responseJson);
    }
    
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Get stored license from database
 */
function getStoredLicense($conn) {
    $stmt = $conn->prepare("SELECT * FROM license_info ORDER BY id DESC LIMIT 1");
    if (!$stmt) return null;
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}

/**
 * Check if revalidation is needed (24 hours)
 */
function licenseNeedsRevalidation($storedLicense) {
    if (!$storedLicense || empty($storedLicense['last_validated_at'])) {
        return true;
    }
    $lastValidated = strtotime($storedLicense['last_validated_at']);
    return (time() - $lastValidated) > 86400;
}

/**
 * Main license check function
 */
function checkLicense($conn, $forceRevalidate = false) {
    $storedLicense = getStoredLicense($conn);
    
    if (!$storedLicense || empty($storedLicense['license_key'])) {
        return [
            'valid' => false,
            'license' => null,
            'error' => 'No license key configured.',
            'error_type' => UM_LICENSE_ERROR_NO_KEY,
            'warning' => null
        ];
    }
    
    $needsRevalidation = $forceRevalidate || licenseNeedsRevalidation($storedLicense);
    
    if ($needsRevalidation) {
        $validation = validateLicenseKey($storedLicense['license_key']);
        
        if ($validation['valid']) {
            storeLicenseInfo($conn, $storedLicense['license_key'], $validation['data']);
            return [
                'valid' => true,
                'license' => getStoredLicense($conn),
                'error' => null,
                'error_type' => UM_LICENSE_ERROR_NONE,
                'warning' => null
            ];
        } else {
            // Update stored license as invalid
            $stmt = $conn->prepare("UPDATE license_info SET is_active = 0, last_validated_at = NOW() WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $storedLicense['id']);
                $stmt->execute();
                $stmt->close();
            }
            
            $result = [
                'valid' => false,
                'license' => getStoredLicense($conn),
                'error' => $validation['error'],
                'error_type' => $validation['error_type'],
                'warning' => null
            ];
            
            // Pass through domain mismatch details
            if (isset($validation['licensed_domain'])) {
                $result['licensed_domain'] = $validation['licensed_domain'];
            }
            if (isset($validation['current_domain'])) {
                $result['current_domain'] = $validation['current_domain'];
            }
            
            return $result;
        }
    }
    
    // Use cached status - but still check domain on every request
    $isValid = $storedLicense['is_active'] && !$storedLicense['is_canceled'];
    
    // Always verify domain matches (even with cached data)
    if ($isValid && !empty($storedLicense['licensed_domain'])) {
        $currentDomain = getCurrentSiteDomain();
        $licensedDomain = strtolower($storedLicense['licensed_domain']);
        
        if ($licensedDomain !== strtolower($currentDomain)) {
            return [
                'valid' => false,
                'license' => $storedLicense,
                'error' => "Domain mismatch: Licensed for '$licensedDomain' but using '$currentDomain'.",
                'error_type' => UM_LICENSE_ERROR_DOMAIN_MISMATCH,
                'licensed_domain' => $licensedDomain,
                'current_domain' => $currentDomain,
                'warning' => null
            ];
        }
    }
    
    // Check expiration
    if ($isValid && !empty($storedLicense['expires_at'])) {
        $expiresAt = strtotime($storedLicense['expires_at']);
        if ($expiresAt && $expiresAt < time()) {
            return [
                'valid' => false,
                'license' => $storedLicense,
                'error' => 'Your license has expired.',
                'error_type' => UM_LICENSE_ERROR_EXPIRED,
                'warning' => null
            ];
        }
    }
    
    return [
        'valid' => $isValid,
        'license' => $storedLicense,
        'error' => $isValid ? null : 'License is not active.',
        'error_type' => $isValid ? UM_LICENSE_ERROR_NONE : UM_LICENSE_ERROR_INACTIVE,
        'warning' => null
    ];
}

/**
 * Mask license key for display
 */
function maskLicenseKey($key) {
    if (strlen($key) <= 8) return str_repeat('*', strlen($key));
    return substr($key, 0, 4) . str_repeat('*', strlen($key) - 8) . substr($key, -4);
}

/**
 * Check if request should be blocked for invalid license
 */
function shouldBlockForLicense($conn) {
    $licenseCheck = checkLicense($conn, false);
    
    if ($licenseCheck['valid']) {
        return ['blocked' => false, 'reason' => null, 'error_type' => UM_LICENSE_ERROR_NONE];
    }
    
    return [
        'blocked' => true,
        'reason' => $licenseCheck['error'] ?? 'Invalid license',
        'error_type' => $licenseCheck['error_type'] ?? UM_LICENSE_ERROR_INVALID_KEY
    ];
}
