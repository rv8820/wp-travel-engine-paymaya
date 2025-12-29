# WTE-Maya

Maya Payment Gateway integration for WP Travel Engine. Accept payments through Maya Checkout including credit/debit cards, QRPh, Maya Wallet, and other payment methods.

## Features

- **Multiple Payment Methods**: Accept credit/debit cards, QRPh, Maya Wallet, and other payment channels
- **Secure Checkout**: PCI DSS compliant hosted checkout page
- **One-time Payments**: Support for full payments and deposit payments
- **Sandbox Testing**: Test mode for development and testing
- **Webhook Support**: Real-time payment status updates
- **PHP Currency**: Optimized for Philippine Peso transactions

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- WP Travel Engine plugin (active)
- Maya merchant account

## Installation

### Manual Installation

1. Download or clone this repository
2. Upload the `wte-maya` folder to `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure the plugin settings (see Configuration section below)

### Via Git

```bash
cd wp-content/plugins
git clone https://github.com/rv8820/wp-travel-engine-paymaya.git wte-maya
```

Then activate the plugin in WordPress admin.

## Configuration

### 1. Get Maya API Keys

**For Sandbox Testing:**
- Use the shared sandbox public key: `pk-Z0OSzLvIcOI2UIvDhdTGVVfRSSeiGStnceqwUE7n0Ah`
- Or generate your own via [Maya Business Manager](https://manager-sandbox.paymaya.com)

**For Production:**
- Contact Maya at business.signup@maya.ph or your Maya Relationship Manager
- Generate production keys via [Maya Manager 1.0](https://manager.paymaya.com)

### 2. Configure Plugin Settings

1. Go to **WP Travel Engine > Settings**
2. Click on the **Maya Payment Gateway** tab
3. Configure the following settings:

| Setting | Description |
|---------|-------------|
| **Gateway Label** | The title shown to customers (default: "Maya") |
| **Description** | Description shown at checkout |
| **Instructions** | Additional instructions for customers |
| **Enable Test Mode** | Enable for sandbox testing, disable for live payments |
| **Public API Key** | Your Maya public API key (starts with `pk-`) |

4. Click **Save Settings**

**Note:** The webhook URL will be displayed in the settings page. Copy it to configure in your Maya Manager account.

### 3. Configure Webhooks (Important!)

Maya sends payment notifications to your webhook URL. You need to configure this in your Maya account:

**Webhook URL Format:**
```
https://yourwebsite.com/?wte-maya-webhook=1&payment_key={PAYMENT_KEY}
```

**How to Set Up:**

1. Log in to Maya Manager
2. Go to **Webhooks** settings
3. Add your webhook URL (replace `yourwebsite.com` with your actual domain)
4. Select the following events:
   - `PAYMENT_SUCCESS`
   - `PAYMENT_FAILED`
   - `PAYMENT_DROPOUT`
   - `PAYMENT_CANCELLED`
5. Save webhook settings

**Note:** The `{PAYMENT_KEY}` will be dynamically replaced by the plugin for each transaction.

## Testing

### Sandbox Mode

1. Enable **Test Mode** in plugin settings
2. Use the sandbox public key
3. Use test cards for payments:

| Card Number | Expiry | CVV | Passkey |
|-------------|--------|-----|---------|
| 4123450131001381 | 12/2030 | 123 | mctest1 |
| 5453010000064154 | 12/2030 | 111 | secbarry1 |
| 3550998167521049 | 12/2030 | 995 | secure35 |

4. Test Maya Wallet:
   - Username: `+639900100900`
   - Password: `Password@1`
   - OTP: `123456`

### Test Scenarios

1. **Successful Payment**
   - Create a booking
   - Select Maya as payment method
   - Complete payment with test card
   - Verify booking status changes to "Paid"

2. **Failed Payment**
   - Use an invalid card number
   - Verify booking remains in pending status

3. **Cancelled Payment**
   - Click cancel during checkout
   - Verify proper handling

4. **Webhook Testing**
   - Monitor webhook logs in Maya Manager
   - Check WordPress debug logs (if `WP_DEBUG` is enabled)

## Production Deployment

Before going live:

1. **Get Production Keys**
   - Contact Maya for production account
   - Generate production public key

2. **Update Settings**
   - Disable **Test Mode**
   - Enter **Production Public Key**
   - Save settings

3. **Verify Webhooks**
   - Ensure webhook URL is configured with production endpoint
   - Test with a small real transaction

4. **Monitor**
   - Check payment notifications
   - Monitor transaction logs
   - Verify booking status updates

## Supported Features

### Payment Types
- ✅ One-time payments (full amount)
- ✅ Deposit payments (partial amount)
- ✅ Balance payments (remaining amount)
- ❌ Recurring payments (not supported)
- ❌ Refunds (not yet implemented)

### WP Travel Engine Integration
- ✅ Booking creation
- ✅ Payment processing
- ✅ Booking status updates
- ✅ Payment confirmation
- ✅ Email notifications (via WTE)

## Troubleshooting

### Payment not processing

1. Check if WP Travel Engine is active
2. Verify API key is correct (starts with `pk-`)
3. Check if Maya gateway is enabled in settings
4. Enable `WP_DEBUG` and check logs:
   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   ```
5. Look for errors in `/wp-content/debug.log`

### Webhook not receiving notifications

1. Verify webhook URL is correct in Maya Manager
2. Check if your site is accessible from internet (not localhost)
3. Verify SSL certificate is valid (webhooks require HTTPS)
4. Check webhook logs in Maya Manager

### Booking status not updating

1. Verify webhooks are configured correctly
2. Check payment status in Maya Manager
3. Review WordPress debug logs
4. Verify payment key is being passed correctly

### API Errors

Common error codes:

| Error | Solution |
|-------|----------|
| 401 Unauthorized | Check API key is correct |
| 400 Bad Request | Check request data format |
| 500 Server Error | Contact Maya support |

## Support

### Plugin Issues
- [GitHub Issues](https://github.com/rv8820/wp-travel-engine-paymaya/issues)

### Maya API Support
- Email: business.signup@maya.ph
- Developer Portal: https://developers.maya.ph

### Documentation
- [Maya Checkout Documentation](https://developers.maya.ph/docs/maya-checkout)
- [WP Travel Engine Documentation](https://docs.wptravelengine.com/)

## Development

### File Structure

```
wte-maya/
├── includes/
│   ├── class-maya-api-client.php   # Maya API communication
│   ├── class-maya-gateway.php      # Payment gateway implementation
│   └── class-maya-settings.php     # Admin settings
├── assets/                         # (Future: CSS/JS files)
├── languages/                      # (Future: Translation files)
├── wte-maya.php                    # Main plugin file
├── README.md                       # This file
└── CUSTOM-PAYMENT-GATEWAY-INTEGRATION.md  # Integration guide
```

### Debugging

Enable WordPress debug mode:

```php
// wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Check logs at `/wp-content/debug.log`

### Filters & Hooks

Currently available filters:

```php
// Modify checkout data before sending to Maya
add_filter( 'wte_maya_checkout_data', function( $data, $booking, $payment ) {
    // Modify $data
    return $data;
}, 10, 3 );
```

## Changelog

### 1.0.0 - 2025-12-29
- Initial release
- Maya Checkout integration
- Sandbox and production support
- Webhook handling
- Admin settings
- PHP currency support
- One-time payments

## License

GPL v2 or later

## Credits

- Developed for WP Travel Engine integration
- Maya Payment Gateway API by Maya Philippines

## Security

- PCI DSS compliant (payments processed on Maya's hosted page)
- No sensitive card data stored on your server
- HTTPS required for webhooks
- API keys stored in WordPress options

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request
