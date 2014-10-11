<?php

namespace SamUser;
 
use Zend\EventManager\EventManager;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;
use Zend\Mvc\MvcEvent;

class Module
{
    public function onBootstrap(MvcEvent $e)
    {
        /*
         * Other module code as usual...
         */

        /*
         * If a user registers, we need to assign them the default 'user'
         * role (and the 'guest' role)
         */

        $zfcServiceEvents = $e->getApplication()
            ->getServiceManager()
            ->get('zfcuser_user_service')
            ->getEventManager();
        $zfcServiceEvents->attach('register.post', function($e) use($e) {

            $class = 'SamUser\Entity\User';
            $em = $e->getApplication()
                ->getServiceManager()
                ->get('doctrine.entitymanager.orm_default');

            /*
             * Get the last inserted user ID
             *
             * This is implemented this way as a) the user is not actually
             * passed back in the event payload, b) I assume that this is
             * counted as a separate transaction because the user is saved
             * in the 'register' event, therefore LAST_INSERT_ID() returns nothing
             *
             * @todo Fork ZfcUser and fix the bug whereby the user is not sent in
             * the event payload (which means we could add user roles to it directory)
             */
            $q = $em->createQuery("SELECT MAX(u.id) FROM " . $class . " u");
            $resultset = $q->getResult();
            $userId = (integer) array_pop(array_pop($resultset));

            // Retrieve the user that was just inserted
            $user = $em->getRepository($class)->find($userId);

            // If the event contained the user (as it looks like it should) you could do this instead
            /*$user = $e->getParam('user');*/

            // Add on the user roles that you want them to have
            $standardUserRole = $em->getRepository('SamUser\Entity\Role')
                ->findOneByRoleId('user');
            $user->addRole($standardUserRole);

            // Save the user
            $em->persist($user);
            $em->flush();

        });

        $zfcuseradminServiceEvents = $e->getApplication()
            ->getServiceManager()
            ->get('zfcuseradmin_user_service')
            ->getEventManager();
        /** @var $zfcuseradminServiceEvents \Zend\EventManager\EventManager */
        


        $zfcuseradminServiceEvents->attach('create.post', function($e) use($e) {

            $class = 'SamUser\Entity\User';
            $em = $e->getApplication()
                ->getServiceManager()
                ->get('doctrine.entitymanager.orm_default');

            /*
             * Get the last inserted user ID
             *
             * This is implemented this way as a) the user is not actually
             * passed back in the event payload, b) I assume that this is
             * counted as a separate transaction because the user is saved
             * in the 'register' event, therefore LAST_INSERT_ID() returns nothing
             *
             * @todo Fork ZfcUser and fix the bug whereby the user is not sent in
             * the event payload (which means we could add user roles to it directory)
             */
            $q = $em->createQuery("SELECT MAX(u.id) FROM " . $class . " u");
            $resultset = $q->getResult();
            $userId = (integer) array_pop(array_pop($resultset));

            // Retrieve the user that was just inserted
            $user = $em->getRepository($class)->find($userId);

            // If the event contained the user (as it looks like it should) you could do this instead
            /*$user = $e->getParam('user');*/

            // Add on the user roles that you want them to have
            $standardUserRole = $em->getRepository('SamUser\Entity\Role')
                ->findOneByRoleId('user');
            $user->addRole($standardUserRole);

            // Save the user
            $em->persist($user);
            $em->flush();

        });
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }
}
