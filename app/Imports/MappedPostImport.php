<?php

namespace App\Imports;

use App\Models\Post;
use App\Models\PostMeta;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Maatwebsite\Excel\Events\AfterChunk;

class MappedPostImport implements ToModel, WithBatchInserts, WithChunkReading, WithEvents, WithHeadingRow, WithUpserts
{
    protected array $mapping;

    protected string $postType;

    protected array $chunkMetas = [];

    public function __construct(array $mapping, string $postType = 'printer')
    {
        $this->mapping = array_filter($mapping);
        $this->postType = $postType;
    }

    public function uniqueBy()
    {
        return 'slug';
    }

    public function model(array $row)
    {
        $mappedData = [];
        $metaData = [];

        foreach ($this->mapping as $fileHeader => $dbColumn) {
            if (array_key_exists($fileHeader, $row)) {
                if (str_starts_with($dbColumn, 'meta:')) {
                    $metaKey = substr($dbColumn, 5);
                    $metaData[$metaKey] = $row[$fileHeader];
                } else {
                    $mappedData[$dbColumn] = $row[$fileHeader];
                }
            }
        }

        if (! isset($mappedData['slug']) || empty($mappedData['slug'])) {
            return null;
        }

        $mappedData['post_type'] = $this->postType;

        $slug = (string) $mappedData['slug'];

        if (! empty($metaData)) {
            $this->chunkMetas[$slug] = $metaData;
        }

        return new Post($mappedData);
    }

    public function batchSize(): int
    {
        return 1000;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function registerEvents(): array
    {
        return [
            AfterChunk::class => function (AfterChunk $event) {
                if (empty($this->chunkMetas)) {
                    return;
                }

                $slugs = array_keys($this->chunkMetas);

                $posts = Post::where('post_type', $this->postType)
                    ->whereIn('slug', $slugs)
                    ->pluck('id', 'slug');

                $metaUpsertData = [];

                foreach ($posts as $slug => $postId) {
                    if (isset($this->chunkMetas[$slug])) {
                        foreach ($this->chunkMetas[$slug] as $metaKey => $metaValue) {
                            $metaUpsertData[] = [
                                'post_id' => $postId,
                                'meta_key' => $metaKey,
                                'meta_value' => is_array($metaValue) ? json_encode($metaValue) : $metaValue,
                            ];
                        }
                    }
                }

                if (! empty($metaUpsertData)) {
                    PostMeta::upsert(
                        $metaUpsertData,
                        ['post_id', 'meta_key'],
                        ['meta_value']
                    );
                }

                $this->chunkMetas = [];
            },
        ];
    }
}
