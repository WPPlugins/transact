## Transact Wordpress Plugin

This plugin integrates transact.io easily in your Wordpress installation.

## Installing this plugin
```
1. Go to /plugins/ folder in your Wordpress installation
2. git clone https://gitlab.com/transact/transact-wordpress.git
3. Log in into Wordpress Dashboard
4. Go to plugins
5. Click on activate
```

## Shortcode manual
1. You can set up a shortcode directly on your post content, this shortcode will override the default button.
2. Is possible to set up new texts for purchase and subscribe buttons, also choose between the 3 model buttons (as in the post settings)
3. You can choose to show "Only Purchase Button", "Only Subscribe button", "Purchase and Subscribe button".
4. Shortcode is [transact_button]
5. If you do not set up any option, it will use default transact buttons.
5. Options are 'button_text' 'subscribe_text', 'button_type'
6. "Purchase and Subscribe button" = 1, "Only Purchase Button" = 2, "Only Subscribe button" = 3
6. Example: [transact_button button_text="purchase me" subscribe_text="subscribe to the site" button_type="1"]