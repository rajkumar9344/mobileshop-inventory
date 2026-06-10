<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Collection;
use Modules\Bin\Entities\Bin;

class SearchBin extends Component
{
    public $query;
    public $search_results;
    public $how_many;

    public function mount() {
        $this->query = '';
        $this->how_many = 5;
        $this->search_results = Collection::empty();
    }

    public function render() {
        return view('livewire.search-bin');
    }

    public function updatedQuery() {
        $query = Bin::query();

        $results = $query->where(function($q) {
                $q->where('bin_name', 'like', '%' . $this->query . '%')
                  ->orWhere('bin_id', 'like', '%' . $this->query . '%');
            })
            ->take($this->how_many)
            ->get();

        $this->search_results = $results;
    }

    public function loadMore() {
        $this->how_many += 5;
        $this->updatedQuery();
    }

    public function resetQuery() {
        $this->query = '';
        $this->how_many = 5;
        $this->search_results = Collection::empty();
    }

    public function selectBin($bin) {
        $this->dispatch('binSelected', $bin);
    }
}
