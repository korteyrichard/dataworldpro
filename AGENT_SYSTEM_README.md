# Agent System Documentation

## Overview

The Agent System is a backward-compatible extension that adds agent/mini-shop functionality with commission and referral systems to the existing e-commerce platform.

## Key Features

### 🏪 Agent Mini Shops
- Users can upgrade to Agent role
- Each agent can create one mini shop with unique URL `/shop/{slug}`
- Agents select products from existing catalog
- Agents set custom pricing (must be >= base price)

### 💰 Commission System
- Commission = agent_price - base_price
- Automatic commission calculation on orders
- Commission lifecycle: pending → available → withdrawn
- Commissions become available when order status = completed

### 🔗 Referral System
- Agents can refer new agents
- 5% referral commission on referred agent's earnings
- Separate tracking and withdrawal system

### 🏦 Withdrawal System
- Agents request withdrawals from available balance
- Admin approval/rejection workflow
- Automatic commission status updates

## Database Schema

### New Tables (Non-Destructive)
- `agent_shops` - Mini shop information
- `agent_products` - Agent-specific product pricing
- `commissions` - Agent earnings tracking
- `withdrawals` - Withdrawal requests
- `referrals` - Agent referral relationships
- `referral_commissions` - Referral earnings

### Modified Tables
- `orders` - Added nullable `agent_id` column

## API Endpoints

### Agent Routes (Requires agent role)
```
GET    /agent/dashboard              - Agent dashboard
POST   /agent/shop                   - Create mini shop
POST   /agent/products               - Add product to shop
POST   /agent/withdrawals            - Request withdrawal
```

### Public Routes
```
GET    /shop/{slug}                  - View mini shop
POST   /shop/{slug}/add-to-cart      - Add to cart from shop
POST   /upgrade-to-agent             - Upgrade to agent
```

### Admin Routes
```
GET    /admin/withdrawals            - View withdrawal requests
POST   /admin/withdrawals/{id}/process - Approve/reject withdrawal
```

## Configuration

### Environment Variables
```env
AGENT_SYSTEM_ENABLED=true
AGENT_REGISTRATION_ENABLED=true
AGENT_SHOP_CREATION_ENABLED=true
COMMISSION_SYSTEM_ENABLED=true
REFERRAL_SYSTEM_ENABLED=true
WITHDRAWAL_SYSTEM_ENABLED=true
DEFAULT_REFERRAL_PERCENTAGE=5.00
COMMISSION_AVAILABILITY_DELAY_HOURS=0
MINIMUM_WITHDRAWAL_AMOUNT=10.00
```

## Deployment

### Safe Deployment
```bash
# Deploy agent system
./deploy_agent_system.sh

# Rollback if needed
./rollback_agent_system.sh
```

### Manual Migration
```bash
php artisan migrate --path=database/migrations/2025_01_28_100000_create_agent_shops_table.php
# ... run all agent migrations in order
```

## Backward Compatibility

### ✅ Preserved Functionality
- All existing checkout flows work unchanged
- Existing orders remain unaffected
- Customer experience unchanged for non-agent orders
- All existing APIs continue to work

### 🔒 Safety Features
- All new tables are isolated
- Nullable foreign keys prevent data loss
- Feature flags allow instant disable
- Observer pattern for automatic commission handling

## Integration Points

### Checkout Integration
The system integrates with existing checkout via `CheckoutIntegrationService`:
- Applies agent pricing when shopping through mini shops
- Sets agent_id on orders automatically
- Maintains session state for agent attribution

### Order Processing
`OrderObserver` automatically handles:
- Commission creation on order placement
- Commission availability on order completion
- Commission reversal on cancellation/refund

## User Roles

### Customer (Default)
- Can browse and purchase normally
- Can upgrade to agent

### Agent
- All customer capabilities
- Can create mini shop
- Can set product pricing
- Can view earnings and request withdrawals
- Can refer other agents

### Admin
- All system access
- Can approve/reject withdrawals
- Can manage agent system

## Security Considerations

- Role-based access control
- Agent pricing validation (>= base price)
- Commission calculations are server-side only
- Withdrawal approval workflow prevents unauthorized access
- Feature flags allow instant system disable

## Monitoring & Maintenance

### Key Metrics to Monitor
- Commission calculation accuracy
- Order processing performance
- Agent registration rates
- Withdrawal processing times

### Regular Tasks
- Monitor commission calculations
- Process withdrawal requests
- Review agent performance
- Update referral percentages if needed

## Troubleshooting

### Common Issues
1. **Agent can't create shop**: Check `AGENT_SHOP_CREATION_ENABLED`
2. **Commissions not calculating**: Verify `COMMISSION_SYSTEM_ENABLED`
3. **Orders missing agent_id**: Check session handling in checkout
4. **Withdrawals not processing**: Verify admin permissions

### Debug Commands
```bash
# Check agent system status
php artisan tinker
>>> config('agent')

# View agent statistics
>>> App\Services\CommissionService::getAgentStats(1)

# Check order commissions
>>> App\Models\Order::find(1)->commissions
```

## Future Enhancements

- Multi-level referral system
- Agent performance analytics
- Automated withdrawal processing
- Commission rate customization per agent
- Bulk product management for agents