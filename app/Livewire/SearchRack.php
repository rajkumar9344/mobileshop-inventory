<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Collection;
use Modules\Rack\Entities\Rack;

class SearchRack extends Component
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
        return view('livewire.search-rack');
    }

    public function updatedQuery() {
        $query = Rack::query();

        $results = $query->where(function($q) {
                $q->where('rack_name', 'like', '%' . $this->query . '%')
                  ->orWhere('rack_id', 'like', '%' . $this->query . '%');
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

    public function selectRack($rack) {
        $this->dispatch('rackSelected', $rack);
    }
}
