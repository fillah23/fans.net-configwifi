<label class="fw-bold">TELNET</label>
<div class="row">
    <div class="col-md-6 mb-3">
        <label>Nama</label>
        <input type="text" name="nama" class="form-control" value="{{ $olt->nama }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label>Tipe</label>
        <select name="tipe" class="form-control" id="editTipeOlt" required>
            <option value="ZTE C300" {{ $olt->tipe == 'ZTE C300' ? 'selected' : '' }}>ZTE C300</option>
            <option value="ZTE C320" {{ $olt->tipe == 'ZTE C320' ? 'selected' : '' }}>ZTE C320</option>
            <option value="HUAWEI MA5630T" {{ $olt->tipe == 'HUAWEI MA5630T' ? 'selected' : '' }}>HUAWEI MA5630T</option>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label>IP</label>
        <input type="text" name="ip" class="form-control" value="{{ $olt->ip }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label>Port</label>
        <input type="number" name="port" class="form-control" value="{{ $olt->port }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label>Card</label>
        <div id="editCardCheckboxes">
            @php
                $cards = explode(',', $olt->card);
            @endphp
            @for($i=0;$i<=18;$i++)
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="card[]" value="{{ $i }}" id="editCard{{ $i }}" {{ in_array($i, $cards) ? 'checked' : '' }}>
                    <label class="form-check-label" for="editCard{{ $i }}">{{ $i }}</label>
                </div>
            @endfor
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <label>User</label>
        <input type="text" name="user" class="form-control" value="{{ $olt->user }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label>Pass</label>
        <input type="text" name="pass" class="form-control" value="{{ $olt->pass }}" required>
    </div>
    <div class="col-12 mb-3">
        <label class="fw-bold">SNMP</label>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label>Community Read</label>
                <input type="text" name="community_read" class="form-control" value="{{ $olt->community_read }}" required>
            </div>
            <div class="col-md-4 mb-3">
                <label>Community Write</label>
                <input type="text" name="community_write" class="form-control" value="{{ $olt->community_write }}" required>
            </div>
            <div class="col-md-4 mb-3">
                <label>Port SNMP</label>
                <input type="text" name="port_snmp" class="form-control" value="{{ $olt->port_snmp }}" required>
            </div>
        </div>
    </div>
</div>
<script>
$('#editTipeOlt').on('change', function() {
    var tipe = $(this).val();
    $('#editCardCheckboxes input[type=checkbox]').prop('checked', false);
    if(tipe === 'ZTE C300') {
        for(let i=2;i<=9;i++) $('#editCard'+i).prop('checked', true);
        for(let i=12;i<=18;i++) $('#editCard'+i).prop('checked', true);
    } else if(tipe === 'ZTE C320') {
        $('#editCard1').prop('checked', true);
        $('#editCard2').prop('checked', true);
    }
});
</script>
