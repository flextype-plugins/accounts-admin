<h1 align="center">Accounts Admin Plugin for <a href="http://flextype.org/">Flextype</a></h1>

<p align="center">
<a href="https://github.com/flextype-plugins/accounts-admin/releases"><img alt="Version" src="https://img.shields.io/github/release/flextype-plugins/accounts-admin.svg?label=version&color=black"></a> <a href="https://github.com/flextype-plugins/accounts-admin"><img src="https://img.shields.io/badge/license-MIT-blue.svg?color=black" alt="License"></a> <a href="https://github.com/flextype-plugins/accounts-admin"><img src="https://img.shields.io/github/downloads/flextype-plugins/accounts-admin/total.svg?color=black" alt="Total downloads"></a> <a href="https://github.com/flextype/flextype"><img src="https://img.shields.io/badge/Flextype-0.9.11-green.svg?color=black" alt="Flextype"></a> <a href="https://crowdin.com/project/flextype-plugin-accounts-admin"><img src="https://d322cqt584bo4o.cloudfront.net/flextype-plugin-accounts-admin/localized.svg?color=black" alt="Crowdin"></a> <a href="https://scrutinizer-ci.com/g/flextype-plugins/accounts-admin?branch=dev&color=black"><img src="https://img.shields.io/scrutinizer/g/flextype-plugins/accounts-admin.svg?branch=dev&color=black" alt="Quality Score"></a> <a href=""><img src="https://img.shields.io/discord/423097982498635778.svg?logo=discord&colorB=728ADA&label=Discord%20Chat" alt="Discord"></a>
</p>

Accounts Admin Plugin to manage users accounts in Flextype Admin Panel.

## DEPENDENCIES

The following dependencies need to be installed for Accounts Admin Plugin.

| Item | Version | Download |
|---|---|---|
| [flextype](https://github.com/flextype/flextype) | 0.9.11 | [download](https://github.com/flextype/flextype/releases) |
| [twig](https://github.com/flextype-plugins/twig) | >=1.0.0 | [download](https://github.com/flextype-plugins/twig/releases) |
| [form](https://github.com/flextype-plugins/form) | >=1.0.0 | [download](https://github.com/flextype-plugins/form/releases) |
| [form-admin](https://github.com/flextype-plugins/form-admin) | >=1.0.0 | [download](https://github.com/flextype-plugins/form-admin/releases) |
| [admin](https://github.com/flextype-plugins/admin) | >=1.0.0 | [download](https://github.com/flextype-plugins/admin/releases) |
| [jquery](https://github.com/flextype-plugins/jquery) | >=1.0.0 | [download](https://github.com/flextype-plugins/jquery/releases) |
| [acl](https://github.com/flextype-plugins/acl) | >=1.0.0 | [download](https://github.com/flextype-plugins/acl/releases) |
| [phpmailer](https://github.com/flextype-plugins/phpmailer) | >=1.0.0 | [download](https://github.com/flextype-plugins/phpmailer/releases) |

## INSTALLATION

1. Download & Install all required dependencies.
2. Create new folder `/project/plugins/accounts-admin`
3. Download Accounts Admin Plugin and unzip plugin content to the folder `/project/plugins/accounts-admin`
4. Copy all fieldsets from `/project/plugins/accounts-admin/fieldsets` to `/project/fieldsets` folder.

### Events

| Event | Description |
|---|---|
| onAccountsAdminUserLoggedIn | Allows plugins to include their own logic when user logs in. |
| onAccountsAdminNewPasswordSended | Allows plugins to include their own logic when new password was sended. |
| onAccountsAdminPasswordReset | Allows plugins to include their own logic when password was reset. |
| onAccountsAdminNewUserRegistered | Allows plugins to include their own logic when new was user registered. |
| onAccountsAdminProfileEdited | Allows plugins to include their own logic when user profile edited. |
| onAccountsAdminLogout | Allows plugins to include their own logic when user logs out. |


## LICENSE
[The MIT License (MIT)](https://github.com/flextype-plugins/accounts-admin/blob/master/LICENSE.txt)
Copyright (c) 2020 [Sergey Romanenko](https://github.com/Awilum)
