<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\Models\AIToolRegistryEntry;
use App\AI\Support\AIToolDefinitionRegistry;
use Database\Seeders\AIToolRegistrySeeder;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * A3 (#6850, tranche garde) — contrat d'outil déclaratif : gardes de
 * cohérence évaluées sur un boot RÉEL de l'application (les providers des
 * modules enregistrent leurs AIToolDefinition) :
 *  1. aucune violation de contrat sur les définitions enregistrées
 *     (AIToolDefinitionRegistry::violations — bc BC-XX, permission, schémas) ;
 *  2. chaque définition déclarée a une entrée active dans `ai_tool_registry`
 *     (sinon l'outil ne sera JAMAIS exposé au LLM : contrat mort) ;
 *  3. la permission déclarée est réellement accordée à au moins un rôle
 *     (config ai.role_permissions — sinon refus systématique à l'exécution).
 * Rétrocompat : les outils legacy de la table SANS définition restent
 * autorisés (refactor progressif, spec A3) — la garde ne contrôle que le
 * sens définition → registre → permissions.
 */
class AIToolContractGuardTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->seed(AIToolRegistrySeeder::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_registered_definitions_have_no_contract_violations(): void
    {
        $violations = AIToolDefinitionRegistry::violations();

        $this->assertSame([], $violations, 'définitions d\'outils en violation de contrat : '.implode(' ', $violations));
    }

    public function test_every_registered_definition_has_an_active_registry_entry(): void
    {
        $definitions = AIToolDefinitionRegistry::all();
        $this->assertNotEmpty($definitions, 'les providers doivent enregistrer leurs définitions au boot');

        /** @var array<int, string> $activeNames */
        $activeNames = AIToolRegistryEntry::query()->where('active', true)->pluck('name')->all();

        foreach (array_keys($definitions) as $name) {
            $this->assertContains(
                $name,
                $activeNames,
                "définition '{$name}' déclarée au boot mais absente de ai_tool_registry (outil jamais exposé — contrat mort)"
            );
        }
    }

    public function test_definition_permissions_are_granted_to_at_least_one_role(): void
    {
        /** @var array<string, list<string>> $rolePermissions */
        $rolePermissions = config('ai.role_permissions', []);
        $granted = [];
        foreach ($rolePermissions as $permissions) {
            foreach ($permissions as $permission) {
                $granted[] = $permission;
            }
        }

        foreach (AIToolDefinitionRegistry::all() as $name => $definition) {
            $this->assertContains(
                $definition->permission,
                $granted,
                "permission '{$definition->permission}' de l'outil '{$name}' accordée à aucun rôle — condition nécessaire (l'enforcement réel passe par la matrice ai.tool_permissions)"
            );
        }
    }
}
