<a name="1.1.2"></a>
# [1.1.2](https://github.com/flextype-plugins/accounts-admin) (2020-06-24)

### Features

* **core** do not allow to delete logged in user account
* **fieldsets** update accounts-admin-add fieldsets, add default role - user

    `accounts-admin-add.yaml`

    ```
    roles:
      title: accounts_admin_roles
      type: tags
      size: 12
      default: user
      validation:
        required: false
    ```

### Bug Fixes

* **lang** fix translates
* **middleware** get supper_admin_registered property directly from the config

<a name="1.1.1"></a>
# [1.1.1](https://github.com/flextype-plugins/accounts-admin) (2020-06-23)

### Bug Fixes
* **lang**: fix translates

<a name="1.1.0"></a>
# [1.1.0](https://github.com/flextype-plugins/accounts-admin) (2020-06-23)

General code update and refactoring with a breaking changes

<a name="1.0.2"></a>
# [1.0.2](https://github.com/flextype-plugins/accounts-admin) (2020-06-22)

### Bug Fixes

* **lang**: fix translates

<a name="1.0.1"></a>
# [1.0.1](https://github.com/flextype-plugins/accounts-admin) (2020-06-22)

### Bug Fixes

* **lang**: fix translates

<a name="1.0.0"></a>
# [1.0.0](https://github.com/flextype-plugins/accounts-admin) (2020-06-21)
* Initial Release
