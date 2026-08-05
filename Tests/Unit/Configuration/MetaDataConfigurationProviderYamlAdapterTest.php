<?php

declare(strict_types=1);

namespace Neos\MetaData\Tests\Unit\Configuration;

use Neos\Flow\I18n\Translator;
use Neos\Flow\Tests\UnitTestCase;
use Neos\MetaData\Configuration\MetaDataConfigurationProviderYamlAdapter;
use Neos\MetaData\Domain\Dto\MetaDataPropertyDefinition;
use Neos\MetaData\Domain\Dto\MetaDataPropertyDefinitions;
use Neos\MetaData\Domain\Dto\MetaDataPropertyName;
use Neos\MetaData\Domain\Dto\MetaDataPropertyType;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests how the `Neos.MetaData.metaDataProperties` settings turn into property definitions
 */
class MetaDataConfigurationProviderYamlAdapterTest extends UnitTestCase
{
    private Translator&MockObject $translator;

    public function setUp(): void
    {
        $this->translator = $this->createMock(Translator::class);
    }

    /**
     * @test
     */
    public function propertiesAreKeyedByTheirName(): void
    {
        $definitions = $this->definitionsFor(['caption' => [], 'copyright' => []]);

        self::assertTrue($definitions->include(MetaDataPropertyName::fromString('caption')));
        self::assertTrue($definitions->include(MetaDataPropertyName::fromString('copyright')));
    }

    /**
     * @return iterable<string, array{configuredType: string|null, expectedType: MetaDataPropertyType}>
     */
    public static function types(): iterable
    {
        yield 'string' => ['configuredType' => 'string', 'expectedType' => MetaDataPropertyType::string];
        yield 'integer' => ['configuredType' => 'integer', 'expectedType' => MetaDataPropertyType::integer];
        yield 'boolean' => ['configuredType' => 'boolean', 'expectedType' => MetaDataPropertyType::boolean];
        yield 'omitted defaults to string' => ['configuredType' => null, 'expectedType' => MetaDataPropertyType::string];
        yield 'unknown defaults to string' => ['configuredType' => 'float', 'expectedType' => MetaDataPropertyType::string];
    }

    /**
     * @dataProvider types
     * @test
     */
    public function theTypeIsParsed(?string $configuredType, MetaDataPropertyType $expectedType): void
    {
        $configuration = $configuredType === null ? [] : ['type' => $configuredType];

        self::assertSame($expectedType, $this->definitionFor($configuration)->type);
    }

    /**
     * @test
     */
    public function theScopeIsLocalizedUnlessConfiguredOtherwise(): void
    {
        self::assertFalse($this->definitionFor([])->globalScope);
        self::assertFalse($this->definitionFor(['globalScope' => false])->globalScope);
        self::assertTrue($this->definitionFor(['globalScope' => true])->globalScope);
    }

    /**
     * @test
     */
    public function aLabelIsUsedVerbatim(): void
    {
        $this->translator->expects(self::never())->method('translateById');

        self::assertSame('Some label', $this->definitionFor(['ui' => ['label' => 'Some label']])->ui->label);
    }

    /**
     * @test
     */
    public function theLiteralLabelI18nIsTranslated(): void
    {
        $this->translator->expects(self::once())
            ->method('translateById')
            ->with('properties.caption', [], null, null, 'Main', 'Neos.MetaData')
            ->willReturn('Bildunterschrift');

        self::assertSame('Bildunterschrift', $this->definitionFor(['ui' => ['label' => 'i18n']])->ui->label);
    }

    /**
     * @test
     */
    public function anUntranslatedLabelFallsBackToThePropertyName(): void
    {
        $this->translator->method('translateById')->willReturn(null);

        self::assertSame('caption', $this->definitionFor(['ui' => ['label' => 'i18n']])->ui->label);
    }

    /**
     * @test
     */
    public function anOmittedLabelFallsBackToThePropertyName(): void
    {
        self::assertSame('caption', $this->definitionFor([])->ui->label);
    }

    /**
     * @test
     */
    public function theEditorAndItsOptionsAreParsed(): void
    {
        $definition = $this->definitionFor([
            'ui' => [
                'inspector' => [
                    'editor' => 'Neos.Neos/Inspector/Editors/TextAreaEditor',
                    'editorOptions' => ['rows' => 7],
                ],
            ],
        ]);

        self::assertSame('Neos.Neos/Inspector/Editors/TextAreaEditor', $definition->ui->editorDefinition->editorType);
        self::assertSame(['rows' => 7], $definition->ui->editorDefinition->options);
    }

    /**
     * A property without any `ui` configuration must not break the parsing
     *
     * @test
     */
    public function propertiesWithoutUiConfigurationAreParsed(): void
    {
        $definition = $this->definitionFor([]);

        self::assertSame('caption', $definition->ui->label);
        self::assertSame([], $definition->ui->editorDefinition->options);
    }

    /**
     * @test
     */
    public function anEmptyConfigurationLeadsToNoDefinitions(): void
    {
        self::assertSame([], iterator_to_array($this->definitionsFor([])));
    }

    /**
     * @test
     */
    public function propertiesConfiguredToNullAreSkipped(): void
    {
        $definitions = $this->definitionsFor(['caption' => [], 'copyright' => null]);

        self::assertTrue($definitions->include(MetaDataPropertyName::fromString('caption')));
        self::assertFalse($definitions->include(MetaDataPropertyName::fromString('copyright')));
    }

    // -----------------------

    /**
     * @param array<string, mixed> $configuration configuration of a single property named "caption"
     */
    private function definitionFor(array $configuration): MetaDataPropertyDefinition
    {
        return $this->definitionsFor(['caption' => $configuration])->get(MetaDataPropertyName::fromString('caption'));
    }

    /**
     * @param array<string, array<string, mixed>|null> $configuration
     */
    private function definitionsFor(array $configuration): MetaDataPropertyDefinitions
    {
        return (new MetaDataConfigurationProviderYamlAdapter($configuration, $this->translator))->getPropertyConfiguration();
    }
}
