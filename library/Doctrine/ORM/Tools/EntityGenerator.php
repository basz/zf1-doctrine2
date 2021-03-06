<?php
/*
 *  $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ORM\Tools;

use Doctrine\ORM\Mapping\ClassMetadataInfo,
    Doctrine\ORM\Mapping\AssociationMapping;

/**
 * Generic class used to generate PHP5 entity classes from ClassMetadataInfo instances
 *
 *     [php]
 *     $classes = $em->getClassMetadataFactory()->getAllMetadata();
 *
 *     $generator = new \Doctrine\ORM\Tools\EntityGenerator();
 *     $generator->setGenerateAnnotations(true);
 *     $generator->setGenerateStubMethods(true);
 *     $generator->setRegenerateEntityIfExists(false);
 *     $generator->setUpdateEntityIfExists(true);
 *     $generator->generate($classes, '/path/to/generate/entities');
 *
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link    www.doctrine-project.org
 * @since   2.0
 * @version $Revision$
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 */
class EntityGenerator
{
    /** The extension to use for written php files */
    private $_extension = '.php';

    /** Whether or not the current ClassMetadataInfo instance is new or old */
    private $_isNew = true;

    /** If isNew is false then this variable contains instance of ReflectionClass for current entity */
    private $_reflection;

    /** Number of spaces to use for indention in generated code */
    private $_numSpaces = 4;

    /** The actual spaces to use for indention */
    private $_spaces = '    ';

    /** The class all generated entities should extend */
    private $_classToExtend;

    /** Whether or not to generation annotations */
    private $_generateAnnotations = false;

    /** Whether or not to generated sub methods */
    private $_generateEntityStubMethods = false;

    /** Whether or not to update the entity class if it exists already */
    private $_updateEntityIfExists = false;

    /** Whether or not to re-generate entity class if it exists already */
    private $_regenerateEntityIfExists = false;

    private static $_template =
'<?php

<namespace><use>
<entityAnnotation>
<entityClassName>
{
<entityBody>
}';

    /**
     * Generate and write entity classes for the given array of ClassMetadataInfo instances
     *
     * @param array $metadatas
     * @param string $outputDirectory 
     * @return void
     */
    public function generate(array $metadatas, $outputDirectory)
    {
        foreach ($metadatas as $metadata) {
            $this->writeEntityClass($metadata, $outputDirectory);
        }
    }

    /**
     * Generated and write entity class to disk for the given ClassMetadataInfo instance
     *
     * @param ClassMetadataInfo $metadata
     * @param string $outputDirectory 
     * @return void
     */
    public function writeEntityClass(ClassMetadataInfo $metadata, $outputDirectory)
    {
        $path = $outputDirectory . '/' . str_replace('\\', DIRECTORY_SEPARATOR, $metadata->name) . $this->_extension;
        $dir = dirname($path);
        if ( ! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->_isNew = ! file_exists($path);

        if ( ! $this->_isNew) {
            require_once $path;
            $this->_reflection = new \ReflectionClass($metadata->name);
        }

        // If entity doesn't exist or we're re-generating the entities entirely
        if ($this->_isNew || ( ! $this->_isNew && $this->_regenerateEntityIfExists)) {
            file_put_contents($path, $this->generateEntityClass($metadata));
    
        // If entity exists and we're allowed to update the entity class
        } else if ( ! $this->_isNew && $this->_updateEntityIfExists) {
            file_put_contents($path, $this->generateUpdatedEntityClass($metadata, $path));
        }
    }

    /**
     * Generate a PHP5 Doctrine 2 entity class from the given ClassMetadataInfo instance
     *
     * @param ClassMetadataInfo $metadata 
     * @return string $code
     */
    public function generateEntityClass(ClassMetadataInfo $metadata)
    {
        $placeHolders = array(
            '<namespace>',
            '<use>',
            '<entityAnnotation>',
            '<entityClassName>',
            '<entityBody>'
        );

        $replacements = array(
            $this->_generateEntityNamespace($metadata),
            $this->_generateEntityUse($metadata),
            $this->_generateAnnotations ? "\n" . $this->_generateEntityAnnotation($metadata) : null,
            $this->_generateEntityClassName($metadata),
            $this->_generateEntityBody($metadata)
        );

        $code = str_replace($placeHolders, $replacements, self::$_template);
        return $code;
    }

    /**
     * Generate the updated code for the given ClassMetadataInfo and entity at path
     *
     * @param ClassMetadataInfo $metadata 
     * @param string $path 
     * @return string $code;
     */
    public function generateUpdatedEntityClass(ClassMetadataInfo $metadata, $path)
    {
        $currentCode = file_get_contents($path);

        $body = $this->_generateEntityBody($metadata);
        
        $last = strrpos($currentCode, '}');
        $code = substr($currentCode, 0, $last) . $body . '}';
        return $code;
    }

    /**
     * Set the number of spaces the exported class should have
     *
     * @param integer $numSpaces 
     * @return void
     */
    public function setNumSpaces($numSpaces)
    {
        $this->_spaces = str_repeat(' ', $numSpaces);
        $this->_numSpaces = $numSpaces;
    }

    /**
     * Set the extension to use when writing php files to disk
     *
     * @param string $extension 
     * @return void
     */
    public function setExtension($extension)
    {
        $this->_extension = $extension;
    }

    /**
     * Set the name of the class the generated classes should extend from
     *
     * @return void
     */
    public function setClassToExtend($classToExtend)
    {
        $this->_classToExtend = $classToExtend;
    }

    /**
     * Set whether or not to generate annotations for the entity
     *
     * @param bool $bool 
     * @return void
     */
    public function setGenerateAnnotations($bool)
    {
        $this->_generateAnnotations = $bool;
    }

    /**
     * Set whether or not to try and update the entity if it already exists
     *
     * @param bool $bool 
     * @return void
     */
    public function setUpdateEntityIfExists($bool)
    {
        $this->_updateEntityIfExists = $bool;
    }

    /**
     * Set whether or not to regenerate the entity if it exists
     *
     * @param bool $bool
     * @return void
     */
    public function setRegenerateEntityIfExists($bool)
    {
        $this->_regenerateEntityIfExists = $bool;
    }

    /**
     * Set whether or not to generate stub methods for the entity
     *
     * @param bool $bool
     * @return void
     */
    public function setGenerateStubMethods($bool)
    {
        $this->_generateEntityStubMethods = $bool;
    }

    private function _generateEntityNamespace(ClassMetadataInfo $metadata)
    {
        if ($this->_hasNamespace($metadata)) {
            return 'namespace ' . $this->_getNamespace($metadata) .';';
        }
    }

    private function _generateEntityUse(ClassMetadataInfo $metadata)
    {
        if ($this->_extendsClass()) {
            return "\n\nuse " . $this->_getClassToExtendNamespace().";\n";
        }
    }

    private function _generateEntityClassName(ClassMetadataInfo $metadata)
    {
        return 'class ' . $this->_getClassName($metadata) .
            ($this->_extendsClass() ? 'extends ' . $this->_getClassToExtendName() : null);
    }

    private function _generateEntityBody(ClassMetadataInfo $metadata)
    {
        $fieldMappingProperties  = $this->_generateEntityFieldMappingProperties($metadata);
        $associationMappingProperties = $this->_generateEntityAssociationMappingProperties($metadata);
        $stubMethods = $this->_generateEntityStubMethods ? $this->_generateEntityStubMethods($metadata) : null;
        $lifecycleCallbackMethods = $this->_generateEntityLifecycleCallbackMethods($metadata);

        $code = '';
        if ($fieldMappingProperties) {
            $code .= $fieldMappingProperties . "\n";
        }
        if ($associationMappingProperties) {
            $code .= $associationMappingProperties . "\n";
        }
        if ($stubMethods) {
            $code .= $stubMethods . "\n";
        }
        if ($lifecycleCallbackMethods) {
            $code .= $lifecycleCallbackMethods . "\n";
        }
        return $code;
    }

    private function _hasProperty($property, $metadata)
    {
        if ($this->_isNew) {
            return false;
        } else {
            return $this->_reflection->hasProperty($property);
        }
    }

    private function _hasMethod($method, $metadata)
    {
        if ($this->_isNew) {
            return false;
        } else {
            return $this->_reflection->hasMethod($method);
        }
    }

    private function _hasNamespace($metadata)
    {
        return strpos($metadata->name, '\\') ? true : false;
    }

    private function _extendsClass()
    {
        return $this->_classToExtend ? true : false;
    }

    private function _getClassToExtend()
    {
        return $this->_classToExtend;
    }

    private function _getClassToExtendName()
    {
        $refl = new \ReflectionClass($this->_getClassToExtend());
        return $refl->getShortName();
    }

    private function _getClassToExtendNamespace()
    {
        $refl = new \ReflectionClass($this->_getClassToExtend());
        return $refl->getNamespaceName() ? $refl->getNamespaceName():$refl->getShortName();        
    }

    private function _getClassName($metadata)
    {
        if ($pos = strrpos($metadata->name, '\\')) {
            return substr($metadata->name, $pos + 1, strlen($metadata->name));
        } else {
            return $metadata->name;
        }
    }

    private function _getNamespace($metadata)
    {
        return substr($metadata->name, 0, strrpos($metadata->name, '\\'));
    }

    private function _generateEntityAnnotation($metadata)
    {
        $lines = array();
        $lines[] = '/**';

        $methods = array(
            '_generateTableAnnotation',
            '_generateInheritanceAnnotation',
            '_generateDiscriminatorColumnAnnotation',
            '_generateDiscriminatorMapAnnotation'
        );

        foreach ($methods as $method) {
            if ($code = $this->$method($metadata)) {
                $lines[] = ' * ' . $code;
            }
        }

        if ($metadata->isMappedSuperclass) {
            $lines[] = ' * @MappedSupperClass';
        } else {
            $lines[] = ' * @Entity';
        }

        if ($metadata->customRepositoryClassName) {
            $lines[count($lines) - 1] .= '(repositoryClass="' . $metadata->customRepositoryClassName . '")';
        }

        if (isset($metadata->lifecycleCallbacks) && $metadata->lifecycleCallbacks) {
            $lines[] = ' * @HasLifecycleCallbacks';
        }

        $lines[] = ' */';

        return implode("\n", $lines);
    }

    private function _generateTableAnnotation($metadata)
    {
        $table = array();
        if ($metadata->primaryTable['name']) {
            $table[] = 'name="' . $metadata->primaryTable['name'] . '"';
        }

        if (isset($metadata->primaryTable['schema'])) {
            $table[] = 'schema="' . $metadata->primaryTable['schema'] . '"';
        }

        return '@Table(' . implode(', ', $table) . ')';
    }

    private function _generateInheritanceAnnotation($metadata)
    {
        if ($metadata->inheritanceType != ClassMetadataInfo::INHERITANCE_TYPE_NONE) {
            return '@InheritanceType("'.$this->_getInheritanceTypeString($metadata->inheritanceType).'")';
        }
    }

    private function _generateDiscriminatorColumnAnnotation($metadata)
    {
        if ($metadata->inheritanceType != ClassMetadataInfo::INHERITANCE_TYPE_NONE) {
            $discrColumn = $metadata->discriminatorValue;
            $columnDefinition = 'name="' . $discrColumn['name']
                . '", type="' . $discrColumn['type']
                . '", length=' . $discrColumn['length'];

            return '@DiscriminatorColumn(' . $columnDefinition . ')';
        }
    }

    private function _generateDiscriminatorMapAnnotation($metadata)
    {
        if ($metadata->inheritanceType != ClassMetadataInfo::INHERITANCE_TYPE_NONE) {
            $inheritanceClassMap = array();

            foreach ($metadata->discriminatorMap as $type => $class) {
                $inheritanceClassMap[] .= '"' . $type . '" = "' . $class . '"';
            }

            return '@DiscriminatorMap({' . implode(', ', $inheritanceClassMap) . '})';
        }
    }

    private function _generateEntityStubMethods(ClassMetadataInfo $metadata)
    {
        $methods = array();

        foreach ($metadata->fieldMappings as $fieldMapping) {
            if ( ! isset($fieldMapping['id']) || ! $fieldMapping['id']) {
                if ($code = $this->_generateEntityStubMethod('set', $fieldMapping['fieldName'], $metadata)) {
                    $methods[] = $code;
                }
            }

            if ($code = $this->_generateEntityStubMethod('get', $fieldMapping['fieldName'], $metadata)) {
                $methods[] = $code;
            }
        }

        foreach ($metadata->associationMappings as $associationMapping) {
            if ($associationMapping instanceof \Doctrine\ORM\Mapping\OneToOneMapping) {
                if ($code = $this->_generateEntityStubMethod('set', $associationMapping->sourceFieldName, $metadata)) {
                    $methods[] = $code;
                }
                if ($code = $this->_generateEntityStubMethod('get', $associationMapping->sourceFieldName, $metadata)) {
                    $methods[] = $code;
                }
            } else if ($associationMapping instanceof \Doctrine\ORM\Mapping\OneToManyMapping) {
                if ($associationMapping->isOwningSide) {
                    if ($code = $this->_generateEntityStubMethod('set', $associationMapping->sourceFieldName, $metadata)) {
                        $methods[] = $code;
                    }
                    if ($code = $this->_generateEntityStubMethod('get', $associationMapping->sourceFieldName, $metadata)) {
                        $methods[] = $code;
                    }
                } else {
                    if ($code = $this->_generateEntityStubMethod('add', $associationMapping->sourceFieldName, $metadata)) {
                        $methods[] = $code;
                    }
                    if ($code = $this->_generateEntityStubMethod('get', $associationMapping->sourceFieldName, $metadata)) {
                        $methods[] = $code;                
                    }
                }
            } else if ($associationMapping instanceof \Doctrine\ORM\Mapping\ManyToManyMapping) {
                if ($code = $this->_generateEntityStubMethod('add', $associationMapping->sourceFieldName, $metadata)) {
                    $methods[] = $code;
                }
                if ($code = $this->_generateEntityStubMethod('get', $associationMapping->sourceFieldName, $metadata)) {
                    $methods[] = $code;
                }
            }
        }

        return implode('', $methods);
    }

    private function _generateEntityLifecycleCallbackMethods(ClassMetadataInfo $metadata)
    {
        if (isset($metadata->lifecycleCallbacks) && $metadata->lifecycleCallbacks) {
            $methods = array();
            foreach ($metadata->lifecycleCallbacks as $name => $callbacks) {
                foreach ($callbacks as $callback) {
                    if ($code = $this->_generateLifecycleCallbackMethod($name, $callback, $metadata)) {
                        $methods[] = $code;
                    }
                }
            }
            return implode('', $methods);
        }
    }

    private function _generateEntityAssociationMappingProperties(ClassMetadataInfo $metadata)
    {
        $lines = array();
        foreach ($metadata->associationMappings as $associationMapping) {
            if ($this->_hasProperty($associationMapping->sourceFieldName, $metadata)) {
                continue;
            }
            if ($this->_generateAnnotations) {
                $lines[] = $this->_generateAssociationMappingAnnotation($associationMapping, $metadata);
            }
            $lines[] = $this->_spaces . 'private $' . $associationMapping->sourceFieldName . ($associationMapping->isManyToMany() ? ' = array()' : null) . ";\n";
        }
        $code = implode("\n", $lines);
        return $code;
    }

    private function _generateEntityFieldMappingProperties(ClassMetadataInfo $metadata)
    {
        $lines = array();
        foreach ($metadata->fieldMappings as $fieldMapping) {
            if ($this->_hasProperty($fieldMapping['fieldName'], $metadata)) {
                continue;
            }
            if ($this->_generateAnnotations) {
                $lines[] = $this->_generateFieldMappingAnnotation($fieldMapping, $metadata);
            }
            $lines[] = $this->_spaces . 'private $' . $fieldMapping['fieldName'] . ";\n";
        }
        $code = implode("\n", $lines);
        return $code;
    }

    private function _generateEntityStubMethod($type, $fieldName, ClassMetadataInfo $metadata)
    {
        $methodName = $type . ucfirst($fieldName);
        if ($this->_hasMethod($methodName, $metadata)) {
            return;
        }

        $method = array();
        $method[] = $this->_spaces . '/**';
        if ($type == 'get') {
            $method[] = $this->_spaces . ' * Get ' . $fieldName;
        } else if ($type == 'set') {
            $method[] = $this->_spaces . ' * Set ' . $fieldName;
        } else if ($type == 'add') {
            $method[] = $this->_spaces . ' * Add ' . $fieldName;
        }
        $method[] = $this->_spaces . ' */';

        if ($type == 'get') {
            $method[] = $this->_spaces . 'public function ' . $methodName . '()';
        } else if ($type == 'set') {
            $method[] = $this->_spaces . 'public function ' . $methodName . '($value)';
        } else if ($type == 'add') {
            $method[] = $this->_spaces . 'public function ' . $methodName . '($value)';        
        }

        $method[] = $this->_spaces . '{';
        if ($type == 'get') {
            $method[] = $this->_spaces . $this->_spaces . 'return $this->' . $fieldName . ';';
        } else if ($type == 'set') {
            $method[] = $this->_spaces . $this->_spaces . '$this->' . $fieldName . ' = $value;';
        } else if ($type == 'add') {
            $method[] = $this->_spaces . $this->_spaces . '$this->' . $fieldName . '[] = $value;';
        }

        $method[] = $this->_spaces . '}';
        $method[] = "\n";

        return implode("\n", $method);
    }

    private function _generateLifecycleCallbackMethod($name, $methodName, $metadata)
    {
        if ($this->_hasMethod($methodName, $metadata)) {
            return;
        }

        $method = array();
        $method[] = $this->_spaces . '/**';
        $method[] = $this->_spaces . ' * @'.$name;
        $method[] = $this->_spaces . ' */';
        $method[] = $this->_spaces . 'public function ' . $methodName . '()';
        $method[] = $this->_spaces . '{';
        $method[] = $this->_spaces . '}';

        return implode("\n", $method)."\n\n";
    }

    private function _generateJoinColumnAnnotation(array $joinColumn)
    {
        $joinColumnAnnot = array();
        if (isset($joinColumn['name'])) {
            $joinColumnAnnot[] = 'name="' . $joinColumn['name'] . '"';
        }
        if (isset($joinColumn['referencedColumnName'])) {
            $joinColumnAnnot[] = 'referencedColumnName="' . $joinColumn['referencedColumnName'] . '"';
        }
        if (isset($joinColumn['unique']) && $joinColumn['unique']) {
            $joinColumnAnnot[] = 'unique=' . ($joinColumn['unique'] ? 'true' : 'false');
        }
        if (isset($joinColumn['nullable'])) {
            $joinColumnAnnot[] = 'nullable=' . ($joinColumn['nullable'] ? 'true' : 'false');
        }
        if (isset($joinColumn['onDelete'])) {
            $joinColumnAnnot[] = 'onDelete=' . ($joinColumn['onDelete'] ? 'true' : 'false');
        }
        if (isset($joinColumn['onUpdate'])) {
            $joinColumnAnnot[] = 'onUpdate=' . ($joinColumn['onUpdate'] ? 'true' : 'false');
        }
        if (isset($joinColumn['columnDefinition'])) {
            $joinColumnAnnot[] = 'columnDefinition="' . $joinColumn['columnDefinition'] . '"';
        }
        return '@JoinColumn(' . implode(', ', $joinColumnAnnot) . ')';
    }

    private function _generateAssociationMappingAnnotation(AssociationMapping $associationMapping, ClassMetadataInfo $metadata)
    {
        $e = explode('\\', get_class($associationMapping));
        $type = str_replace('Mapping', '', end($e));
        $typeOptions = array();
        if (isset($associationMapping->targetEntityName)) {
            $typeOptions[] = 'targetEntity="' . $associationMapping->targetEntityName . '"';
        }
        if (isset($associationMapping->mappedBy)) {
            $typeOptions[] = 'mappedBy="' . $associationMapping->mappedBy . '"';
        }
        if ($associationMapping->hasCascades()) {
            $cascades = array();
            if ($associationMapping->isCascadePersist) $cascades[] = '"persist"';
            if ($associationMapping->isCascadeRemove) $cascades[] = '"remove"';
            if ($associationMapping->isCascadeDetach) $cascades[] = '"detach"';
            if ($associationMapping->isCascadeMerge) $cascades[] = '"merge"';
            if ($associationMapping->isCascadeRefresh) $cascades[] = '"refresh"';
            $typeOptions[] = 'cascade={' . implode(',', $cascades) . '}';            
        }
        if (isset($associationMapping->orphanRemoval) && $associationMapping->orphanRemoval) {
            $typeOptions[] = 'orphanRemoval=' . ($associationMapping->orphanRemoval ? 'true' : 'false');
        }

        $lines = array();
        $lines[] = $this->_spaces . '/**';
        $lines[] = $this->_spaces . ' * @' . $type . '(' . implode(', ', $typeOptions) . ')';

        if (isset($associationMapping->joinColumns) && $associationMapping->joinColumns) {
            $lines[] = $this->_spaces . ' * @JoinColumns({';

            $joinColumnsLines = array();
            foreach ($associationMapping->joinColumns as $joinColumn) {
                if ($joinColumnAnnot = $this->_generateJoinColumnAnnotation($joinColumn)) {
                    $joinColumnsLines[] = $this->_spaces . ' *   ' . $joinColumnAnnot;
                }
            }
            $lines[] = implode(",\n", $joinColumnsLines);
            $lines[] = $this->_spaces . ' * })';
        }

        if (isset($associationMapping->joinTable) && $associationMapping->joinTable) {
            $joinTable = array();
            $joinTable[] = 'name="' . $associationMapping->joinTable['name'] . '"';
            if (isset($associationMapping->joinTable['schema'])) {
                $joinTable[] = 'schema="' . $associationMapping->joinTable['schema'] . '"';
            }

            $lines[] = $this->_spaces . ' * @JoinTable(' . implode(', ', $joinTable) . ',';

            $lines[] = $this->_spaces . ' *   joinColumns={';
            foreach ($associationMapping->joinTable['joinColumns'] as $joinColumn) {
                $lines[] = $this->_spaces . ' *     ' . $this->_generateJoinColumnAnnotation($joinColumn);
            }
            $lines[] = $this->_spaces . ' *   },';

            $lines[] = $this->_spaces . ' *   inverseJoinColumns={';
            foreach ($associationMapping->joinTable['inverseJoinColumns'] as $joinColumn) {
                $lines[] = $this->_spaces . ' *     ' . $this->_generateJoinColumnAnnotation($joinColumn);
            }
            $lines[] = $this->_spaces . ' *   }';

            $lines[] = $this->_spaces . ' * )';
        }

        if (isset($associationMapping->orderBy)) {
            $lines[] = $this->_spaces . ' * @OrderBy({';
            foreach ($associationMapping->orderBy as $name => $direction) {
                $lines[] = $this->_spaces . ' *     "' . $name . '"="' . $direction . '",'; 
            }
            $lines[count($lines) - 1] = substr($lines[count($lines) - 1], 0, strlen($lines[count($lines) - 1]) - 1);
            $lines[] = $this->_spaces . ' * })';
        }

        $lines[] = $this->_spaces . ' */';

        return implode("\n", $lines);
    }

    private function _generateFieldMappingAnnotation(array $fieldMapping, ClassMetadataInfo $metadata)
    {
        $lines = array();
        $lines[] = $this->_spaces . '/**';

        $column = array();
        if (isset($fieldMapping['columnName'])) {
            $column[] = 'name="' . $fieldMapping['columnName'] . '"';
        }
        if (isset($fieldMapping['type'])) {
            $column[] = 'type="' . $fieldMapping['type'] . '"';
        }
        if (isset($fieldMapping['length'])) {
            $column[] = 'length=' . $fieldMapping['length'];
        }
        if (isset($fieldMapping['precision'])) {
            $column[] = 'precision=' .  $fieldMapping['precision'];
        }
        if (isset($fieldMapping['scale'])) {
            $column[] = 'scale=' . $fieldMapping['scale'];
        }
        if (isset($fieldMapping['nullable'])) {
            $column[] = 'nullable=' .  var_export($fieldMapping['nullable'], true);
        }
        if (isset($fieldMapping['columnDefinition'])) {
            $column[] = 'columnDefinition="' . $fieldMapping['columnDefinition'] . '"';
        }
        if (isset($fieldMapping['options'])) {
            $options = array();
            foreach ($fieldMapping['options'] as $key => $value) {
                $value = var_export($value, true);
                $value = str_replace("'", '"', $value);
                $options[] = ! is_numeric($key) ? $key . '=' . $value:$value;
            }
            if ($options) {
                $column[] = 'options={' . implode(', ', $options) . '}';
            }
        }
        if (isset($fieldMapping['unique'])) {
            $column[] = 'unique=' . var_export($fieldMapping['unique'], true);
        }
        $lines[] = $this->_spaces . ' * @Column(' . implode(', ', $column) . ')';
        if (isset($fieldMapping['id']) && $fieldMapping['id']) {
            $lines[] = $this->_spaces . ' * @Id';
            if ($generatorType = $this->_getIdGeneratorTypeString($metadata->generatorType)) {
                $lines[] = $this->_spaces.' * @GeneratedValue(strategy="' . $generatorType . '")';
            }
            if ($metadata->sequenceGeneratorDefinition) {
                $sequenceGenerator = array();
                if (isset($metadata->sequenceGeneratorDefinition['sequenceName'])) {
                    $sequenceGenerator[] = 'sequenceName="' . $metadata->sequenceGeneratorDefinition['sequenceName'] . '"';
                }
                if (isset($metadata->sequenceGeneratorDefinition['allocationSize'])) {
                    $sequenceGenerator[] = 'allocationSize="' . $metadata->sequenceGeneratorDefinition['allocationSize'] . '"';
                }
                if (isset($metadata->sequenceGeneratorDefinition['initialValue'])) {
                    $sequenceGenerator[] = 'initialValue="' . $metadata->sequenceGeneratorDefinition['initialValue'] . '"';
                }
                $lines[] = $this->_spaces . ' * @SequenceGenerator(' . implode(', ', $sequenceGenerator) . ')';
            }
        }
        if (isset($fieldMapping['version']) && $fieldMapping['version']) {
            $lines[] = $this->_spaces . ' * @Version';
        }
        $lines[] = $this->_spaces . ' */';

        return implode("\n", $lines);
    }

    private function _getInheritanceTypeString($type)
    {
        switch ($type)
        {
            case ClassMetadataInfo::INHERITANCE_TYPE_NONE:
                return 'NONE';
            break;

            case ClassMetadataInfo::INHERITANCE_TYPE_JOINED:
                return 'JOINED';
            break;
            
            case ClassMetadataInfo::INHERITANCE_TYPE_SINGLE_TABLE:
                return 'SINGLE_TABLE';
            break;
            
            case ClassMetadataInfo::INHERITANCE_TYPE_TABLE_PER_CLASS:
                return 'PER_CLASS';
            break;
        }
    }

    private function _getChangeTrackingPolicyString($policy)
    {
        switch ($policy)
        {
            case ClassMetadataInfo::CHANGETRACKING_DEFERRED_IMPLICIT:
                return 'DEFERRED_IMPLICIT';
            break;
            
            case ClassMetadataInfo::CHANGETRACKING_DEFERRED_EXPLICIT:
                return 'DEFERRED_EXPLICIT';
            break;
            
            case ClassMetadataInfo::CHANGETRACKING_NOTIFY:
                return 'NOTIFY';
            break;
        }
    }

    private function _getIdGeneratorTypeString($type)
    {
        switch ($type)
        {
            case ClassMetadataInfo::GENERATOR_TYPE_AUTO:
                return 'AUTO';
            break;
            
            case ClassMetadataInfo::GENERATOR_TYPE_SEQUENCE:
                return 'SEQUENCE';
            break;
            
            case ClassMetadataInfo::GENERATOR_TYPE_TABLE:
                return 'TABLE';
            break;
            
            case ClassMetadataInfo::GENERATOR_TYPE_IDENTITY:
                return 'IDENTITY';
            break;
        }
    }
}