<?php

namespace Polyel\Container;

use ReflectionClass;

class Container
{
    // Holds all the registered class instances
    private $container = [];

    // Can be passed an array of starting classes to resolve and be placed in the container
    public function __construct($classesToResolve = null)
    {
        if(isset($classesToResolve))
        {
            // Resolve each class inside the array that was passed in
            foreach($classesToResolve as $class)
            {
                // Each class is then stored inside the container
                $this->checkForDependencies($class);
            }
        }
    }

    // Recursively checks for class dependencies, resolves them and creates class instances.
    private function checkForDependencies($classToResolve, $returnClassOnly = false)
    {
        // Return when the class already exists inside the container
        if($this->get($classToResolve))
        {
            // Stops classes being overwritten again if called to be resolved
            return;
        }

        // Using Reflection, load the class up...
        $classReflection = new ReflectionClass($classToResolve);

        // Get the class constructor method...
        $constructor = $classReflection->getConstructor();

        // A constructor is required to perform constructor dependency injection.
        if(isset($constructor))
        {
            // Collect all the constructor parameters, then we know the class requirements...
            $constructorParameters = $constructor->getParameters();

            // Used to store all the requested class dependencies.
            $dependencyList = [];

            // Loop through each constructor parameter and check if it exists in the container already.
            foreach($constructorParameters as $param)
            {
                // Get the parameter type, returns NULL on not type
                $dependencyToCheck = $param->getType();

                // Pass onto the next paramater if the type is not defined e.g. could be a normal variable
                if(!isset($dependencyToCheck))
                {
                    continue;
                }

                // Only get the class name and not the type.
                $dependencyToCheck = $dependencyToCheck->getName();

                // For when the class has a dependency that does not exist yet.
                if(!$this->get($dependencyToCheck))
                {
                    // Recursively resolve and check further dependencies before we resolve the final class.
                    $resolvedClass = $this->checkForDependencies($dependencyToCheck, $returnClassOnly);
                }

                // Using the constructors parameters, we store all the required dependencies here.
                $dependencyList[] = $this->get($dependencyToCheck) ?? $resolvedClass;
            }
        }
        else
        {
            // No constructor means we don't need any class arguments/ dependencies to create a new instance
            $dependencyList = null;
        }

        /*
         * Finally, we resolve the class, passing in any arguments (or not) to the
         * constructor with what it requires in order to initiate a new class.
         * The $dependencyList contains the class arguments in the form of a array.
         */
        return $this->resolveClassDependency($classToResolve, $dependencyList, $returnClassOnly);
    }

    // Resolve a single class dependency and store it in the container
    private function resolveClassDependency($dependencyToResolve, $classArgs, $returnClassOnly)
    {
        // Using Reflection, load the class up...
        $classDependency = new ReflectionClass($dependencyToResolve);

        if(isset($classArgs))
        {
            // Crate an instance using and passing in any constructor arguments.
            $newClassInstance = $classDependency->newInstanceArgs($classArgs);
        }
        else
        {
            // No constructor, means no constructor dependency injection.
            $newClassInstance = $classDependency->newInstanceWithoutConstructor();
        }

        // Whether  to just return the class by itself or to place it inside the service container
        if($returnClassOnly)
        {
            // Return a fully resolved class
            return $newClassInstance;
        }
        else
        {
            // Finally store the newly created instance inside the container.
            $this->container[$dependencyToResolve] = $newClassInstance;
        }
    }

    // Public facing function to externally resolve a class
    public function resolveClass($classToResolve)
    {
        $this->checkForDependencies($classToResolve);

        return $this->get($classToResolve);
    }

    // Used to perform method injection within a class
    public function resolveMethodInjection($class, $methodToResolve)
    {
        // Get the class we want to perform method injection on
        $class = $this->get($class);

        // Check that the class does exist...
        if(isset($class))
        {
            // Reflect the class...
            $class = new ReflectionClass($class);

            // Check the class has the method we want to inject on
            if($class->hasMethod($methodToResolve))
            {
                // Get the methods parameters, if any...
                $methodParams = $class->getMethod($methodToResolve)->getParameters();

                // Loop through each method param and resolve them if needed
                $methodDependencyList = [];
                foreach($methodParams as $param)
                {
                    // Cannot process a parameter if it does not have a type
                    if(!$param->hasType())
                    {
                        // Skip current param because it does not have a type thus, cannot check what class it is
                        continue;
                    }

                    // Getting the name gets the full namespace
                    $methodDependencyName = $param->getType()->getName();
                    $methodDependency = $this->get($methodDependencyName);

                    // Sometimes the method param dependency might not exist yet, try to resolve it...
                    if(!isset($methodDependency))
                    {
                        // Try to resolve a class that may not have been initiated
                        $this->checkForDependencies($methodDependencyName);
                        $methodDependency = $this->get($methodDependencyName);
                    }

                    // Once the method dependency has been resolve, add it to the list to return later...
                    $methodDependencyList[] = $methodDependency;
                }

                // Return any method dependencies
                return $methodDependencyList;
            }
        }

        // Return false when the class we want to perform method inject on does not exist
        return false;
    }

    // Used to retrieve class instances from the container.
    public function get($className)
    {
        // Return a class instance if it exists inside the container
        if(array_key_exists($className, $this->container))
        {
            return $this->container[$className];
        }

        // For when the requested class does not exist inside the container...
        return null;
    }

    // Used to resolve and create a new class without storing it inside the container
    public function new($class)
    {
        // Returns a fully resolved class by itself and does not place it inside the service container
        return $this->checkForDependencies($class, true);
    }

    // A function to return all the names of the classes inside the container, full namespace is returned.
    public function list()
    {
        // Loop through to get all the FQNS for each item inside the container.
        $containerList = [];
        foreach ($this->container as $className => $object)
        {
            $containerList[] = $className;
        }

        return $containerList;
    }
}