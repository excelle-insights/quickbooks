<?php

namespace ExcelleInsights\QuickBooks\Utils;

class VendorNameGenerator
{
    /**
     * Generate a unique display name for vendors with duplicate names
     * 
     * @param string $baseName The original supplier name
     * @param string $taxId Company PIN/Tax ID
     * @param string $email Supplier email
     * @return string Unique display name
     */
    public static function generateUniqueDisplayName(string $baseName, string $taxId = null, string $email = null): string
    {
        $baseName = trim($baseName);
        
        // If we have a tax ID, append it for uniqueness
        if (!empty($taxId)) {
            return $baseName . ' (' . $taxId . ')';
        }
        
        // If we have an email, use domain part
        if (!empty($email) && strpos($email, '@') !== false) {
            $domain = substr($email, strpos($email, '@') + 1);
            return $baseName . ' (' . $domain . ')';
        }
        
        // Fallback: append timestamp
        return $baseName . ' (' . date('Y-m-d') . ')';
    }
    
    /**
     * Create a vendor identifier hash for internal tracking
     * 
     * @param array $vendorData
     * @return string
     */
    public static function createVendorHash(array $vendorData): string
    {
        $identifiers = [
            $vendorData['display_name'] ?? '',
            $vendorData['tax_identifier'] ?? '',
            $vendorData['email'] ?? '',
        ];
        
        return md5(implode('|', array_filter($identifiers)));
    }
    
    /**
     * Validate if vendor data has sufficient unique identifiers
     * 
     * @param array $vendorData
     * @return array ['valid' => bool, 'missing' => array]
     */
    public static function validateUniqueIdentifiers(array $vendorData): array
    {
        $missing = [];
        
        if (empty($vendorData['display_name'])) {
            $missing[] = 'display_name';
        }
        
        // At least one of these should be present for uniqueness
        $hasUniqueField = !empty($vendorData['tax_identifier']) || 
                         !empty($vendorData['email']) || 
                         !empty($vendorData['phone']);
        
        if (!$hasUniqueField) {
            $missing[] = 'unique_identifier (tax_identifier, email, or phone)';
        }
        
        return [
            'valid' => empty($missing),
            'missing' => $missing
        ];
    }
}