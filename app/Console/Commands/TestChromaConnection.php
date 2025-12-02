<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ChromaService;
use Exception;

class TestChromaConnection extends Command
{
    protected $signature = 'chroma:test';
    protected $description = 'Test Chroma Cloud connection and basic operations';

    public function handle()
    {
        $this->info('Testing Chroma Cloud connection...');
        $this->newLine();

        try {
            $chroma = app(ChromaService::class);
            
            // Test 1: Connection
            $this->info('1. Testing connection...');
            $result = $chroma->testConnection();
            
            if (!$result['success']) {
                $this->error('✗ Connection failed: ' . $result['message']);
                return 1;
            }
            
            $this->info('✓ Connection successful!');
            $this->info('   Host: ' . $result['host']);
            $this->newLine();

            // Test 2: Collection creation
            $this->info('2. Testing collection creation...');
            $testCollection = 'test_collection_' . time();
            $collection = $chroma->getOrCreateCollection($testCollection);
            $this->info('✓ Collection created: ' . $collection['name']);
            $this->newLine();

            // Test 3: Vector upload
            $this->info('3. Testing vector upload...');
            $testVector = [
                'id' => 'test_' . time(),
                'values' => array_fill(0, 1536, 0.1),
                'metadata' => [
                    'test' => true,
                    'content' => 'This is a test document for Chroma Cloud migration'
                ]
            ];
            
            $chroma->addDocuments($testCollection, [$testVector]);
            $this->info('✓ Vector uploaded successfully');
            $this->newLine();

            // Test 4: Query
            $this->info('4. Testing vector query...');
            $queryResult = $chroma->query($testCollection, array_fill(0, 1536, 0.1), ['top_k' => 1]);
            $this->info('✓ Query successful');
            $this->info('   Results found: ' . count($queryResult['matches'] ?? []));
            $this->newLine();

            // Test 5: Stats
            $this->info('5. Testing collection stats...');
            $stats = $chroma->getCollectionStats($testCollection);
            $this->info('✓ Stats retrieved');
            $this->info('   Vector count: ' . $stats['count']);
            $this->newLine();

            // Test 6: Delete
            $this->info('6. Testing vector deletion...');
            $chroma->deleteDocuments($testCollection, [$testVector['id']]);
            $this->info('✓ Vector deleted successfully');
            $this->newLine();

            // Summary
            $this->info('═══════════════════════════════════════');
            $this->info('🎉 All tests passed!');
            $this->info('Chroma Cloud is ready to use.');
            $this->info('═══════════════════════════════════════');

            return 0;

        } catch (Exception $e) {
            $this->error('✗ Test failed: ' . $e->getMessage());
            $this->newLine();
            $this->error('Stack trace:');
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}
