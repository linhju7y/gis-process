<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Clean up</title>
    <link href="/assets/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/all.min.css" rel="stylesheet" type="text/css" />
    <link rel="icon" href="/favicon.ico" />

    <script src="/assets/jquery-3.5.1.min.js"></script>
    <script src="/assets/popper.min.js"></script>
    <script src="/assets/bootstrap.min.js"></script>

    <head>

    <body>
        <div class="container">
            <form method="POST" enctype="multipart/form-data">
                <select name="level1" class="form-control"></select><br />
                <select name="level2" class="form-control"></select><br />
                <select name="level3" class="form-control"></select><br />
                <select name="level4" class="form-control"></select><br />
                <input type="submit" class="form-control btn btn-primary" value="Upload" />
            </form>
            <p>{!! $echo !!}</p>
        </div>
        <script>
            $(function() {
                $("input[type=submit]").click(function() {
                    $(this).attr("disabled", true);
                    $("form").submit();
                });

                function getSubdivision(id, el) {
                    $.ajax({
                        method: "GET",
                        url: "<?= __HOST__ ?>api/get-subdivision/" + (id || 0),
                        success: function(data) {
                            if (data.status && data.data.length) {
                                var src = data.data;
                                var html = "";
                                $.each(data.data, function(i, o) {
                                    html += "<option value='" + o.id + "'>" + o.label + "</option>";
                                });
                                if (html.length) {
                                    $(el).html(html);
                                    $(el).trigger("change");
                                }
                            }
                        }
                    })
                }

                getSubdivision(0, "select[name=level1]");
                $("select[name=level1]").change(function() {
                    var val = $(this).val();
                    getSubdivision(val, "select[name=level2]");
                });
                $("select[name=level2]").change(function() {
                    var val = $(this).val();
                    getSubdivision(val, "select[name=level3]");
                });
                $("select[name=level3]").change(function() {
                    var val = $(this).val();
                    getSubdivision(val, "select[name=level4]");
                });
            });
        </script>
    </body>

</html>