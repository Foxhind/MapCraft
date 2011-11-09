### About ###


### How to start developing ###

1. (optional) create virtual machine (vmware/virtualbox) and install Debian 6.0
there. Another tested OS: Ubuntu 11.10. Any other is not tested yet and can
cause some bugs during deployment.

2. Install dependencies:

    ```
    apt-get install nginx apache2 erlang git postgres php5 php5-pgsql php5-gd
    ```

    You don't have to modify system configs of apache and nginx to make it work.
    There are scripts that will start you own daemons on non-standard ports.

3. Few more steps to prepare database. It will create _superuser_ database role
   that matches your unix login. So you can use database without any problems.

    ```
    su -  (or sudo -i on Ubuntu)
    su - postgres
    createuser -s <your-login>
    ```

4. Clone sources.

    ```
    git clone git://github.com/Foxhind/MapCraft.git mapcraft
    cd mapcraft
    ```

    Remember that you can allways register on github and
    [fork](http://help.github.com/fork-a-repo/) this project, this way you 
    can freely commit and publish your improvements.

5. Now prepare environment and start all:

    ```
    ./scripts/env init
    ./scritps/env all-start
    ```

    The first command will create necessary config files and initialize
    database. You can do those steps separately:
    ```./scripts/env gen-configs```, ```./scripts/env reset-db```.

    The second command will start nginx apache and hub daemons with developers
    configs. You can control them separately: ```./scripts/apache```,
    ```./scripts/nginx```, ```./scripts hub```

6. Test it: ```http://<IP-of-your-VM>:2000```

### See also ###

* TODO: http://piratepad.net/YFP0rrxp4G
* API: http://piratepad.net/ApS1jZmqR0

