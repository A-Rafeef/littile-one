<?php

/**
 * Feature Gate Engine
 * Progressive disclosure manager
 */
class FeatureGate
{
    /**
     * Check if a feature is available for a user
     */
    public static function can(int $userId, string $feature): bool
    {
        $db = getDB();

        // 1. Check explicit feature flags (overrides or usage unlocks)
        $stmt = $db->prepare("SELECT 1 FROM user_feature_flags WHERE user_id = ? AND feature_key = ?");
        $stmt->execute([$userId, $feature]);
        if ($stmt->fetch()) {
            return true;
        }

        // 2. Check plan entitlements
        $stmt = $db->prepare("
            SELECT p.features 
            FROM users u 
            JOIN plans p ON u.plan_id = p.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if ($row) {
            $features = json_decode($row['features'], true);
            if (isset($features[$feature]) && $features[$feature] === true) {
                return true;
            }
        }

        // 3. Check hardcoded limits/logic if not in plan (optional fallback)
        // 'stock_tracking' is available to everyone
        if ($feature === 'stock_tracking') {
            return true;
        }

        return false;
    }

    /**
     * Unlock a feature for a user
     */
    public static function unlock(int $userId, string $feature, string $reason): void
    {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT IGNORE INTO user_feature_flags (user_id, feature_key, unlock_reason) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $feature, $reason]);
    }

    /**
     * Check and trigger usage-based unlocks
     */
    public static function checkUsageTriggers(int $userId): void
    {
        $db = getDB();

        // Get total products across all stores for this user
        $stmt = $db->prepare("
            SELECT SUM(product_count) as total_products 
            FROM stores 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        $productCount = $result['total_products'] ?? 0;

        // Trigger: Variants (Unlock after 5 products)
        if ($productCount >= 5) {
            if (!self::can($userId, 'variants')) {
                self::unlock($userId, 'variants', 'usage');
            }
        }

        // Trigger: Featured Products (Unlock after 10 products)
        if ($productCount >= 10) {
            if (!self::can($userId, 'featured_products')) {
                self::unlock($userId, 'featured_products', 'usage');
            }
        }
    }
}
