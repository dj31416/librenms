@extends('layouts.librenmsv1')

@section('title', __('Locations'))

@section('content')
    <script id="location-graph-template" type="text/x-handlebars-template">
        <tr class="bg-fixer-@{{id}}"></tr>
        <tr id="location-graph-@{{id}}" class="location_graphs">
            <td colspan=8>
                {!! $graph_template !!}
            </td>
        </tr>
    </script>


    <div id="locations-panel" class="panel panel-default">
        <div class="panel-heading"><h4 class="panel-title">@lang('Locations')</h4></div>
        <div class="panel-body">
            <div class="table-responsive">
                <table id="locations" class="table table-hover table-condensed table-striped">
                    <thead>
                    <tr>
                        <th data-column-id="location" data-formatter="location" data-order="asc">@lang('Location')</th>
                        <th data-column-id="coordinates" data-formatter="coordinates" data-sortable="false">@lang('Coordinates')</th>
                        <th data-column-id="devices" data-formatter="primaryLabel">@lang('Devices')</th>
                        <th data-column-id="network" data-formatter="defaultLabel">@lang('Network')</th>
                        <th data-column-id="servers" data-formatter="defaultLabel">@lang('Servers')</th>
                        <th data-column-id="firewalls" data-formatter="defaultLabel">@lang('Firewalls')</th>
                        <th data-column-id="down" data-formatter="down">@lang('Down')</th>
                        <th data-column-id="actions" data-formatter="actions" data-sortable="false">@lang('Actions')</th>
                    </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="edit-location" tabindex="-1" role="dialog" aria-labelledby="edit-location-title">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="edit-location-title">Edit Location :: <span></span></h4>
                </div>
                <div class="modal-body">
                    <div id="location-edit-map" style="width: 568px; height: 400px;"></div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">@lang('Cancel')</button>
                    <button type="button" class="btn btn-primary" id="save-location">@lang('Save changes')</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css')
    <style>
        #locations-panel>.panel-body {
            padding: 0;
        }
    </style>
@endsection

@section('javascript')
    <script src="js/leaflet.js"></script>
    <script src="js/L.Control.Locate.min.js"></script>
    <script>
        var locationMap = null;
        var locationMarker = null;
        var locationId = 0;
        var locationsGraphTemplate = '';
        var locations_grid = null;

        $(document).ready(function () {
            locations_grid = $("#locations").bootgrid({
                ajax: true,
                rowCount: [25, 50, 100, -1],
                url: "ajax/table/location",
                formatters: {
                    "location": function (column, row) {
                        return '<a href=/devices/location=' + row.id + '>' + row.location + '</a>';
                    },
                    "coordinates": function (column, row) {
                        if (row.lat && row.lng) {
                            return row.lat + ', ' + row.lng;
                        }
                        return '@lang('N/A')';
                    },
                    "down": function (column, row) {
                        if (row.down > 0) {
                            return '<span class="label label-danger">' + row.down + '</span>';
                        }

                        return '<span class="label label-success">' + row.down + '</span>';
                    },
                    "primaryLabel": function (column, row) {
                        return '<span class="label label-primary">' + row[column.id] + '</span>';
                    },
                    "defaultLabel": function (column, row) {
                        return '<span class="label label-default">' + row[column.id] + '</span>';
                    },
                    "actions": function (column, row) {
                        var buttons = '<div style="white-space:nowrap"><button type="button" class="btn btn-xs btn-primary" onclick="toggle_location_graphs(' + row.id + ', this)"';
                        if (row.devices < 1) {
                            buttons += ' disabled title="@lang('Location must have devices to show graphs')"';
                        }
                        buttons += '><i class="fa fa-area-chart" aria-hidden="true"></i><span class="hidden-sm"> @lang('Traffic')</span></button>';

                        @admin
                        buttons += ' <button type="button" class="btn btn-xs btn-default" data-id="' + row.id +
                            '" data-location="' + row.location + '" data-lat="' + row.lat + '" data-lng="' + row.lng +
                            '" onclick="$(\'#edit-location\').modal(\'show\', this)"><i class="fa fa-pencil" aria-hidden="true"></i>' +
                            '<span class="hidden-sm"> @lang('Edit')</span></button>';

                        buttons += ' <button type="button" class="btn btn-xs btn-danger" onclick="delete_location(' + row.id + ')"';
                        if (row.devices > 0) {
                            buttons += ' disabled title="@lang('Cannot delete locations used by devices')"';
                        }
                        buttons += '><i class="fa fa-trash" aria-hidden="true"></i><span class="hidden-sm">  @lang('Delete')</span></button>';
                        @endadmin

                        buttons += '</div>';

                        return buttons;
                    }
                }
            });

            var modal = $('#edit-location');

            modal.on('show.bs.modal', function (e) {
                $('#edit-location-title>span').text($(e.relatedTarget).data('location'))
            });

            modal.on('shown.bs.modal', function (e) {
                var $btn = $(e.relatedTarget);
                var location = new L.LatLng($btn.data('lat'), $btn.data('lng'));
                locationId = $btn.data('id');

                if (locationMap === null) {
                    locationMap = init_map('location-edit-map', '{{ $maps_engine }}', '{{ $maps_api }}');
                    locationMarker = init_map_marker(locationMap, location);
                }

                var zoom = 17;
                if (location.lat === 0 && location.lng === 0) {
                    zoom = 1;
                }

                // move the map (which will trigger a move event and update the marker
                locationMarker.setLatLng(location);
                locationMap.setView(location, zoom);
            });

            $('#save-location').click(function () {
                update_location(locationId, locationMarker.getLatLng(), function (success) {
                    if (success) {
                        modal.modal('hide');
                        locations_grid.bootgrid('reload');
                    }
                });
            });
            locationsGraphTemplate = Handlebars.compile(document.getElementById("location-graph-template").innerHTML);
        });

        function delete_location(locationId) {
            $.ajax({
                method: 'DELETE',
                url: "ajax/location/" + locationId
            }).success(function () {
                locations_grid.bootgrid('reload');
                toastr.success('@lang('Location deleted')');
            }).error(function (e) {
                var data = e.responseJSON;
                if (data && data.hasOwnProperty('id')) {
                    toastr.error(data.id.join(' '));
                } else {
                    toastr.error('@lang('Failed to delete location'): ' + e.statusText)
                }
            });
        }

        function toggle_location_graphs(locationId, source) {
            var $btn = $(source);
            var $row = $btn.closest('tr');
            if ($btn.hasClass('active')) {
                // hide
                $btn.removeClass('active');
                $('#location-graph-' + locationId).hide();
                $('#bg-fix-' + locationId).hide();
            } else {
                // show
                $btn.addClass('active');
                $existing = $('#location-graph-' + locationId);
                if ($existing.length) {
                    $existing.show();
                    $('#bg-fix-' + locationId).show();
                } else {
                    var html = locationsGraphTemplate({id: locationId});
                    $(html).insertAfter($row);
                }
            }
        }
    </script>
@endsection
