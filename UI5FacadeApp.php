<?php
namespace exface\UI5Facade;

use exface\Core\Interfaces\InstallerInterface;
use exface\Core\Facades\AbstractHttpFacade\HttpFacadeInstaller;
use exface\Core\CommonLogic\Model\App;
use exface\Core\Factories\FacadeFactory;
use exface\Core\Facades\AbstractPWAFacade\ServiceWorkerInstaller;
use exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller;


class UI5FacadeApp extends App
{
    private $exportPath = null;
    
    /**
     * {@inheritdoc}
     * 
     * An additional installer is included to condigure the routing for the HTTP facades.
     * 
     * @see App::getInstaller($injected_installer)
     */
    public function getInstaller(InstallerInterface $injected_installer = null)
    {
        $installer = parent::getInstaller($injected_installer);
        
        // Install routes
        $facade = FacadeFactory::createFromString('exface.UI5Facade.UI5Facade', $this->getWorkbench());
        $tplInstaller = new HttpFacadeInstaller($this->getSelector());
        $tplInstaller->setFacade($facade);
        $installer->addInstaller($tplInstaller);
        
        // Install SQL tables for UI5 export projects
        $modelLoader = $this->getWorkbench()->model()->getModelLoader();
        $exportProjectsDataSource = $modelLoader->getDataConnection();
        $installerClass = get_class($modelLoader->getInstaller()->getInstallers()[0]);
        $schema_installer = new $installerClass($this->getSelector());
        if ($schema_installer instanceof AbstractSqlDatabaseInstaller) {
            $schema_installer
                ->setFoldersWithMigrations(['InitDB','Migrations'])
                ->setDataConnection($exportProjectsDataSource)
                ->setMigrationsTableName('_migrations_ui5facade');
        }
        $installer->addInstaller($schema_installer); 
        
        // Install ServiceWorker
        $installer->addInstaller(ServiceWorkerInstaller::fromConfig($this->getSelector(), $this->getConfig(), $facade->getFileVersionHash()));
        
        return $installer;
    }
}
?>