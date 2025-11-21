````markdown
``` 
# NGCS — n8n & WhatsApp integration

This document describes the changes in the NGCS full build and how to connect n8n and the WhatsApp Cloud API.

## Summary of the changes
- Standardized DB table names: `wp_ngcs_batches` and `wp_ngcs_batch_rows` (previously `wp_ngcs_excel_batches` / `wp_ngcs_excel_rows`).
- Added a WP-CLI migration tool: `wp ngcs migrate` to rename existing tables safely.
- Added a secure webhook endpoint: `/wp-json/ngcs/v1/webhook/status` (uses header `X-NGCS-SECRET`).

## n8n -> WP (status updates)
- Configure n8n to send POST JSON to: `https://your-site.tld/wp-json/ngcs/v1/webhook/status`
- Add HTTP header `X-NGCS-SECRET` with the secret value you set in WP option `ngcs_webhook_secret`.
- Payload example:

```
{
  "row_id": 123,
  "status": "delivered",
  "message_id": "wamid:...",
  "error": ""
}
```

## WP -> n8n (send selected rows)
- WP sends to existing n8n endpoints such as `https://n8n.ngcs.co.il/webhook/send-selected` and `.../excel-upload`.
- If you change payload format in the future, update n8n's HTTP request node to match.

## Migration steps
1. Backup your DB.
2. Install WP-CLI on your server (if not present).
3. Run: `wp ngcs migrate` from the WP root. This will attempt to rename old tables to the new canonical names.
4. Verify data integrity.
5. Remove or keep the migration tool as needed.

## Security
- Set a secret with `update_option('ngcs_webhook_secret','your_secret_here');` or use a small admin UI to set it.
- Always use HTTPS for webhooks.

## Notes
- If you prefer not to rename tables, the migration tool can be adapted to copy data instead of renaming.
- After migration, update any external integrations that referenced the old table names or endpoints.
````
