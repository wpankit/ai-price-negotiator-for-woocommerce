# Contributing to AI Price Negotiator for WooCommerce

Thanks for helping improve the plugin. This guide explains how to report problems, suggest features and send code.

## Questions and support

Please ask on the [WordPress.org support forum](https://wordpress.org/support/plugin/ai-price-negotiator-for-woocommerce/). GitHub issues are for bugs and feature ideas.

## Reporting a bug

Search the [open issues](https://github.com/wpankit/ai-price-negotiator-for-woocommerce/issues) first. If the bug is new, open a [bug report](https://github.com/wpankit/ai-price-negotiator-for-woocommerce/issues/new?template=bug_report.yml) with the steps to reproduce it and your plugin, WordPress, WooCommerce and PHP versions.

Found a security issue? Please don't open an issue; follow [SECURITY.md](SECURITY.md) instead.

## Suggesting a feature

Open a [feature request](https://github.com/wpankit/ai-price-negotiator-for-woocommerce/issues/new?template=feature_request.yml) and describe the problem it would solve.

## Translating

Translations are managed on [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/ai-price-negotiator-for-woocommerce/).

## Contributing code

### Set up

1. Run WordPress with WooCommerce locally, for example with [Local](https://localwp.com/), [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) or [DDEV](https://ddev.com/).
2. Fork this repository and clone your fork into `wp-content/plugins/ai-price-negotiator-for-woocommerce`.
3. Run `composer install` to get the coding standards tools.
4. Activate the plugin and add an OpenAI API key under **AI Negotiator → Settings**.

### Make your change

- Create a branch from `main`. `main` is protected, so every change arrives through a pull request.
- Keep each pull request to one topic.
- Follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/). `composer lint` must pass; `composer format` fixes most issues.
- The code must keep working on PHP 7.4 and WordPress 5.8.
- Prefix new functions, classes, options and hooks with `aipn` / `AIPN_`, and use the `ai-price-negotiator-for-woocommerce` text domain.
- Escape output, sanitize input, and check capabilities and nonces.
- If your change alters what is sent to OpenAI or any other service, update **External services** in `readme.txt`.

### Test it

Before opening the pull request, try the flows your change touches, for example:

- a negotiation on the Checkout block and on the classic checkout,
- a guest and a logged-in shopper,
- a deal being agreed and the coupon applied,
- the Settings and Analytics screens.

### Open the pull request

Fill in the template: what changed, why, and how you tested it. The checks (Coding Standards, PHP Lint and Plugin Check) must pass before the pull request is merged.

## Code of Conduct

This project follows the [Contributor Covenant](CODE_OF_CONDUCT.md). By taking part, you agree to follow it.

## License

By contributing, you agree that your contributions are licensed under the [GPL-2.0-or-later](LICENSE) license.
