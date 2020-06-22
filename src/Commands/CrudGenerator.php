<?php

namespace bebold\CrudGenerator\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;


class CrudGenerator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crud:generate {json}';

    /**
     * The console command description.
     *
     * @var string
     */
	protected $description = 'Command description';
	
	protected $index = -1;


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
		$jsonFile = $this->argument('json');
		if(!file_exists($jsonFile)){
			echo 'config file not exist';
			return;
		} 

		$config = file_get_contents($jsonFile); 
		try {
			$config = json_decode($config);
		} catch (\Exception $e) {
			print_r($e);
			return;
		}

		$this->checkJson($config);

		foreach ($config as $className => $classDefinition) {

			$this->index++;

			$names = $this->getClassNames($className, $classDefinition);
			$this->createController($names, $classDefinition);
			$this->createModel($names, $classDefinition);
			$this->createResource($names, $classDefinition);
			$this->createMigration($names, $classDefinition);
			$this->createRequests($names, $classDefinition);
		}
	}

	function checkJson($config){
		foreach ($config as $className => $classDefinition) {

			if(!$className){
				print_r ('name is required for class');
				return;
			}

			if(gettype($classDefinition) !== 'object'){
				print_r('Class ' . $className . ' : definition of the class must be an object');
				return;
			}

			if(!count((array)$classDefinition)){
				print_r('Class ' . $className . ' : has no definition');
				return;
			}

			if(!property_exists($classDefinition, 'properties')){
				print_r('Class ' . $className . ' must have a properties definitions');
				return;
			}

			if(gettype($classDefinition->properties) !== 'object'){
				print_r('Class ' . $className . ' : Properties value must be an object');
				return;
			}

			if(!count((array)$classDefinition->properties)){
				print_r('Class ' . $className . ' must contain at least one property');
				return;
			}

			if(property_exists($classDefinition, 'timestamps') && gettype($classDefinition->timestamps) !== 'boolean'){
				print_r('Class ' . $className . ' : "timestamps" option must be a boolean');
				return;
			}

			if(property_exists($classDefinition, 'softDeletes') && gettype($classDefinition->softDeletes) !== 'boolean'){
				print_r('Class ' . $className . ' : "softDeletes" option must be a boolean');
				return;
			}

			foreach ($classDefinition->properties as $propertyName => $propertyDefinition) {
				if(!$propertyName){
					print_r('Class ' . $className . ' : name is required for property');
					return;
				}

				if(!property_exists($propertyDefinition, 'type')){
					print_r('Class ' . $className . '->' . $propertyName . ' : "type" definition is required');
					return;
				}

				if(gettype($propertyDefinition->type) !== 'string'){
					print_r('Class ' . $className . '->' . $propertyName . ' : "type" must be a string');
					return;
				}

				if(property_exists($propertyDefinition, 'nullable') && gettype($propertyDefinition->nullable) !== 'boolean'){
					print_r('Class ' . $className . '->' . $propertyName . ' : "nullable" option must be a boolean');
					return;
				}

				if(property_exists($propertyDefinition, 'default') && !in_array(gettype($propertyDefinition->default), array('boolean', 'integer', 'double', 'string')) ){
					print_r('Class ' . $className . '->' . $propertyName . ' : "default" option value is not valid ["boolean", "integer", "double", "string"]');
					return;
				}

				if(property_exists($propertyDefinition, 'typeParams') && gettype($propertyDefinition->typeParams) !== 'array'){
					print_r('Class ' . $className . '->' . $propertyName . ' : "typeParams" option must be an array');
					return;
				}

				if(property_exists($propertyDefinition, 'typeParams')){
					foreach ($propertyDefinition->typeParams as $value) {
						if(!in_array(gettype($value), array('integer', 'array'))){
							print_r('Class ' . $className . '->' . $propertyName . ' : "typeParams" contains not valid values ["integer", "array"]');
							return;
						}
					}
				}

				if(property_exists($propertyDefinition, 'validator')){
					$validator = $propertyDefinition->validator;
					if(gettype($validator) !== 'object'){
						print_r('Class ' . $className . '->' . $propertyName . ' : "validator" option must be an object');
						return;
					}

					if(!property_exists($validator, 'rules')){
						print_r('Class ' . $className . '->' . $propertyName . ' : "validator" option must have an "rules" property');
						return;
					}

					if(gettype($validator->rules) !== 'string'){
						print_r('Class ' . $className . '->' . $propertyName . ' : "validator->rules" must be a string');
						return;
					}

					if(property_exists($validator, 'messages')){
						$messages = $validator->messages;

						if(gettype($messages) !== 'object'){
							print_r('Class ' . $className . '->' . $propertyName . ' : "validator->messages" must be an object');
							return;
						}

						foreach ($messages as $attribute => $message) {
							if(!$attribute){
								print_r('Class ' . $className . '->' . $propertyName . ' : "validator->messages" all messages must have an attribute');
								return;
							}

							if(gettype($message) !== 'string'){
								print_r('Class ' . $className . '->' . $propertyName . ' : "validator->messages" all messages value must be a string');
								return;
							}
						}
					}
					
				}
			}
		}
	}

	function getClassNames($name, $classDefinition){

		$plural = property_exists($classDefinition, 'plural') ? $classDefinition->plural : $name . 's';

		$upperName = $name;
		$upperName[0] = strtoupper($upperName[0]);
		$lowerName = $name;
		$lowerName[0] = strtolower($lowerName[0]);

		$upperPlural = $plural;
		$upperPlural[0] = strtoupper($upperPlural[0]);
		$lowerPlural = $plural;
		$lowerPlural[0] = strtolower($lowerPlural[0]);
		
		$controllerName = $upperName . 'Controller';
		$modelName = $upperName;
		$tableName = $lowerPlural;
		$resourceName = $upperName . 'Resource';
		$resourcesName = $upperPlural . 'Resource';
		$createRequest = $upperName . 'CreateRequest';
		$updateRequest = $upperName . 'UpdateRequest';

		$names = array(
			'Dummy' => $modelName,
			'Dummies' => $upperPlural,
			'DummiesTable' => $tableName,
			"DummyResource" => $resourceName,
			"DummiesResource" => $resourcesName,
			'DummyController' => $controllerName,
			'DummyVar' => $lowerName,
			'CreateDummyRequest' => $createRequest,
			'UpdateDummyRequest' => $updateRequest,
		);

		return $names;
	}
	
	function createController($names, $definition){
		$controllersFolderPath = app_path('Http/Controllers');
		if (!is_dir( $controllersFolderPath )) mkdir($controllersFolderPath);  

		$controllerPath = 	$controllersFolderPath . '/' . $names['DummyController'] . '.php';
		$createMethod = '';
		$updateMethod = '';
		
		foreach ($definition->properties as $propertyName => $propertyDefinition) {
			if(strtolower($propertyName) === 'id') continue;

			$createMethod .=  '$' . $names['DummyVar'] . '->' . Str::snake($propertyName) . ' = $request->' . Str::camel($propertyName) . ';' . "\n\t\t";
			$updateMethod .=  '$' . $names['DummyVar'] . '->' . Str::snake($propertyName) . ' = $request->' . Str::camel($propertyName) . ';' . "\n\t\t";
		}

		$controllerTmp = file_get_contents(__DIR__ . '/template/controller.tmp'); 

		$controllerTmp = str_replace('{Dummy}', $names['Dummy'], $controllerTmp);
		$controllerTmp = str_replace('{Dummies}', $names['Dummies'], $controllerTmp);
		$controllerTmp = str_replace('{DummyResource}', $names['DummyResource'], $controllerTmp);
		$controllerTmp = str_replace('{DummiesResource}', $names['DummiesResource'], $controllerTmp);
		$controllerTmp = str_replace('{DummyController}', $names['DummyController'], $controllerTmp);
		$controllerTmp = str_replace('{DummyVar}', $names['DummyVar'], $controllerTmp);
		$controllerTmp = str_replace('{CreateDummyRequest}', $names['CreateDummyRequest'], $controllerTmp);
		$controllerTmp = str_replace('{UpdateDummyRequest}', $names['UpdateDummyRequest'], $controllerTmp);
		$controllerTmp = str_replace('{DummyCreate}', $createMethod, $controllerTmp);
		$controllerTmp = str_replace('{DummyUpdate}', $updateMethod, $controllerTmp);

		file_put_contents($controllerPath, $controllerTmp);

		$this->updateRoutes($names);
	}

	function updateRoutes($names){
		$routePath = base_path('routes/api.php');

		$routes = file_get_contents($routePath);
		$routes .= "\n\n";
		
		$routesTmp = file_get_contents(__DIR__ . '/template/routes.tmp'); 
		$routesTmp = str_replace('{Dummy}', $names['Dummy'], $routesTmp);
		$routesTmp = str_replace('{DummyVar}', $names['DummyVar'], $routesTmp);
		$routesTmp = str_replace('{DummyController}', $names['DummyController'], $routesTmp);
		
		$routes .= $routesTmp;

		file_put_contents($routePath, $routes);
	}

	function createModel($names, $definition){
		$modelsFolderPath = app_path('Models');
		if (!is_dir( $modelsFolderPath )) mkdir($modelsFolderPath);     

		$modelPath = $modelsFolderPath . '/' . $names['Dummy'] . '.php';

		$modelTmp = file_get_contents(__DIR__ . '/template/model.tmp'); 
		$modelTmp = str_replace('{Dummy}', $names['Dummy'], $modelTmp);
		$modelTmp = str_replace('{DummiesTable}', $names['DummiesTable'], $modelTmp);

		  
		file_put_contents($modelPath, $modelTmp);
	}

	function createResource($names, $definition){
		$resourcesFolderPath = app_path('Http/Resources');
		if (!is_dir( $resourcesFolderPath )) mkdir($resourcesFolderPath);     

		$resourcePath = $resourcesFolderPath . '/' . $names['Dummy'] . '.php';
		$resourcesPath = $resourcesFolderPath . '/' . $names['Dummies'] . '.php';
		
		$resourceJsonConvert = '';
		foreach ($definition->properties as $propertyName => $propertyDefinition) {
			$resourceJsonConvert .=  '\'' . Str::camel($propertyName) . '\' => $this->' . Str::snake($propertyName) . ',' . "\n\t\t\t";
		}

		$resourceTmp = file_get_contents(__DIR__ . '/template/resource.tmp'); 
		$resourceTmp = str_replace('{Dummy}', $names['Dummy'], $resourceTmp);
		$resourceTmp = str_replace('{DummyResourceJsonConvert}', $resourceJsonConvert, $resourceTmp);

		file_put_contents($resourcePath, $resourceTmp);
		

		$resourcesTmp = file_get_contents(__DIR__ . '/template/resources.tmp'); 
		$resourcesTmp = str_replace('{Dummy}', $names['Dummy'], $resourcesTmp);
		$resourcesTmp = str_replace('{Dummies}', $names['Dummies'], $resourcesTmp);
		$resourcesTmp = str_replace('{DummyResource}', $names['DummyResource'], $resourcesTmp);
		$resourcesTmp = str_replace('{DummyVar}', $names['DummyVar'], $resourcesTmp);

		file_put_contents($resourcesPath, $resourcesTmp);
	}

	function createMigration($names, $definition){
		$migrationsFolderPath = base_path('database/migrations');
		if (!is_dir($migrationsFolderPath)) mkdir($migrationsFolderPath);
		
		$migrationPath = $migrationsFolderPath . '/' . date('Y_m_d_Hi') . (date('s') + $this->index) . '_create_' . $names['DummiesTable'] . '_table.php';
		$migrationClassName = 'Create' . $names['Dummies'] . 'Table';

		$createTable = '';
		foreach ($definition->properties as $propertyName => $propertyDefinition) {
			$createTable .=  '$table->' . $propertyDefinition->type . '(\'' . Str::snake($propertyName) . '\'';

			if(property_exists($propertyDefinition, 'typeParams')){
				foreach ($propertyDefinition->typeParams as $value) {
					$createTable .= ', ';
					switch (gettype($value)){
						case 'string':
							$createTable .= '\'' .  $value . '\'';
						break;
						default: 
							$createTable .= $value;
						break;
					}
				}

				$createTable = substr($createTable, 0, -1);
			}
			
			$createTable .=  ')';
			
			if(property_exists($propertyDefinition, 'default')){

				$createTable .= '->default(';

				switch (gettype($propertyDefinition->default)){
					case 'string':
						$createTable .= '\'' . $propertyDefinition->default . '\'';
						break;
					default: 
						$createTable .= $propertyDefinition->default;
						break;
				}

				$createTable .= ')';
			}

			if(property_exists($propertyDefinition, 'nullable')){
				if($propertyDefinition->nullable) $createTable .= '->nullable()';
			}

			$createTable .= ';' . "\n\t\t\t";
		}

		if(property_exists($definition, 'timestamps')){
			if($definition->timestamps) $createTable .= '$table->timestamps();' . "\n\t\t\t";
		}

		if(property_exists($definition, 'softDeletes')){
			if($definition->softDeletes) $createTable .= '$table->softDeletes();' . "\n\t\t\t";
		}

		$migrationTmp = file_get_contents(__DIR__ . '/template/migration.tmp'); 
		$migrationTmp = str_replace('{DummiesCreateTableClass}', $migrationClassName, $migrationTmp);
		$migrationTmp = str_replace('{DummiesTable}', $names['DummiesTable'], $migrationTmp);
		$migrationTmp = str_replace('{DummyCreateTable}', $createTable, $migrationTmp);

		file_put_contents($migrationPath, $migrationTmp);
	}

	function createRequests($names, $definition){
		$requestsFolderPath = app_path('Http/requests');
		if (!is_dir($requestsFolderPath)) mkdir($requestsFolderPath);

		$createRequestClass = 'Create' . $names['Dummy'] . 'Request';
		$updateRequestClass = 'Update' . $names['Dummy'] . 'Request';

		$createRequestPath = $requestsFolderPath . '/' . $createRequestClass . '.php';
		$updateRequestPath = $requestsFolderPath . '/' . $updateRequestClass . '.php';

		$rules = '';
		$messages_strings = '';

		foreach ($definition->properties as $propertyName => $propertyDefinition) {
			if(!property_exists($propertyDefinition, 'validator')) continue;

			$validator = $propertyDefinition->validator;
			$rules .= '\'' . $propertyName . '\' => \'' . $validator->rules . '\',' . "\n\t\t\t";

			if(property_exists($validator, 'messages')){
				
				$messages = $validator->messages;
				
				foreach ($messages as $attribute => $message) {
					$messages_strings .= '\'' . $propertyName . '.' . $attribute . '\' => \'' . $message . '\',' . "\n\t\t\t";
				}
			}
		}

		$createRequestTmp = file_get_contents(__DIR__ . '/template/request.tmp'); 
		$createRequestTmp = str_replace('{DummyRequestClass}', $createRequestClass, $createRequestTmp);
		$createRequestTmp = str_replace('{DummyRules}', $rules, $createRequestTmp);
		$createRequestTmp = str_replace('{DummyMessages}', $messages_strings, $createRequestTmp);

		file_put_contents($createRequestPath, $createRequestTmp);

		$updateRequestTmp = file_get_contents(__DIR__ . '/template/request.tmp'); 
		$updateRequestTmp = str_replace('{DummyRequestClass}', $updateRequestClass, $updateRequestTmp);
		$updateRequestTmp = str_replace('{DummyRules}', $rules, $updateRequestTmp);
		$updateRequestTmp = str_replace('{DummyMessages}', $messages_strings, $updateRequestTmp);

		file_put_contents($updateRequestPath, $updateRequestTmp);
	}
}
